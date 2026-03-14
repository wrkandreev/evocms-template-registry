<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Support;

class ModuleSettingsManager
{
    public function isApiEnabled(array $apiConfig): bool
    {
        $store = new ApiAccessStateStore();
        return $store->isEnabled((bool) ($apiConfig['enabled'] ?? true));
    }

    public function readToken(array $apiConfig): string
    {
        return trim((string) ($apiConfig['access_token'] ?? ''));
    }

    /** @return array{success:bool,warning:?string} */
    public function save(string $apiState, string $token, string $pluginState): array
    {
        $store = new ApiAccessStateStore();
        $store->setEnabled($apiState === 'enabled');

        if (!$this->writeAccessTokenToConfig($this->normalizeToken($token))) {
            return [
                'success' => false,
                'warning' => null,
            ];
        }

        $pluginManager = new RegistryAutogeneratePluginManager();
        $warning = null;

        if ($pluginState === 'not_installed') {
            $pluginManager->uninstall();
        } else {
            $result = $pluginManager->install($pluginState === 'enabled');
            $missingEvents = (array) ($result['missing_events'] ?? []);
            if ($missingEvents !== []) {
                $warning = 'Missing system events: ' . implode(', ', $missingEvents);
            }
        }

        return [
            'success' => true,
            'warning' => $warning,
        ];
    }

    private function normalizeToken(string $token): string
    {
        $token = trim($token);
        if (strlen($token) > 512) {
            return substr($token, 0, 512);
        }

        return $token;
    }

    private function writeAccessTokenToConfig(string $token): bool
    {
        $path = $this->configFilePath();
        if (!is_file($path)) {
            $packageConfigPath = dirname(__DIR__, 2) . '/config/template-registry.php';
            if (!is_file($packageConfigPath)) {
                return false;
            }

            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return false;
            }

            if (@copy($packageConfigPath, $path) === false) {
                return false;
            }
        }

        $content = @file_get_contents($path);
        if (!is_string($content) || $content === '') {
            return false;
        }

        $count = 0;
        $updated = preg_replace_callback(
            '/([\'\"]access_token[\'\"]\s*=>\s*)([^,]*)(,)/',
            static function (array $matches) use ($token): string {
                return $matches[1] . var_export($token, true) . $matches[3];
            },
            $content,
            1,
            $count
        );

        if ($count !== 1 || !is_string($updated)) {
            return false;
        }

        if ($updated === $content) {
            return true;
        }

        return file_put_contents($path, $updated, LOCK_EX) !== false;
    }

    private function configFilePath(): string
    {
        $corePath = defined('MODX_CORE_PATH')
            ? rtrim((string) constant('MODX_CORE_PATH'), '/')
            : rtrim((string) (getcwd() ?: ''), '/');

        if (!str_ends_with($corePath, '/core')) {
            $corePath .= '/core';
        }

        return $corePath . '/config/template-registry.php';
    }
}
