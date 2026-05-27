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

    public function isWriteEnabled(array $apiConfig): bool
    {
        return (bool) ($apiConfig['write_enabled'] ?? false);
    }

    public function readWriteToken(array $apiConfig): string
    {
        return trim((string) ($apiConfig['write_access_token'] ?? ''));
    }

    /** @return array{success:bool,warning:?string,generated_access_token:?string,generated_write_access_token:?string} */
    public function save(
        string $apiState,
        string $writeState,
        string $pluginState,
        bool $generateAccessToken = false,
        bool $generateWriteToken = false
    ): array
    {
        $store = new ApiAccessStateStore();
        $store->setEnabled($apiState === 'enabled');

        $generatedAccessToken = $generateAccessToken ? $this->generateToken() : null;
        $generatedWriteToken = $generateWriteToken ? $this->generateToken() : null;

        $settings = [
            'write_enabled' => $writeState === 'enabled',
        ];
        if ($generatedAccessToken !== null) {
            $settings['access_token'] = $generatedAccessToken;
        }
        if ($generatedWriteToken !== null) {
            $settings['write_access_token'] = $generatedWriteToken;
        }

        if (!$this->writeApiSettingsToConfig($settings)) {
            return [
                'success' => false,
                'warning' => null,
                'generated_access_token' => null,
                'generated_write_access_token' => null,
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
            'generated_access_token' => $generatedAccessToken,
            'generated_write_access_token' => $generatedWriteToken,
        ];
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** @param array<string,mixed> $values */
    private function writeApiSettingsToConfig(array $values): bool
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

        $updated = $content;
        foreach ($values as $key => $value) {
            $count = 0;
            $updatedValue = preg_replace_callback(
                '/([\'\"]' . preg_quote((string) $key, '/') . '[\'\"]\s*=>\s*)([^,]*)(,)/',
                static function (array $matches) use ($value): string {
                    return $matches[1] . var_export($value, true) . $matches[3];
                },
                $updated,
                1,
                $count
            );

            if ($count === 0) {
                $updatedValue = $this->insertApiConfigKey($updated, (string) $key, $value);
                if ($updatedValue === null) {
                    return false;
                }
            }

            if (!is_string($updatedValue)) {
                return false;
            }

            $updated = $updatedValue;
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

        return $corePath . '/custom/config/template-registry.php';
    }

    private function insertApiConfigKey(string $content, string $key, mixed $value): ?string
    {
        $replacement = "        '" . $key . "' => " . var_export($value, true) . ",\n        'admin_prefix' =>";
        $count = 0;
        $updated = str_replace("        'admin_prefix' =>", $replacement, $content, $count);
        if ($count === 1) {
            return $updated;
        }

        $count = 0;
        $updated = preg_replace(
            "/('api'\s*=>\s*\[\s*)(.*?)(\n\s*\],)/s",
            "$1$2\n        '" . $key . "' => " . var_export($value, true) . ",$3",
            $content,
            1,
            $count
        );

        if ($count === 1 && is_string($updated)) {
            return $updated;
        }

        return null;
    }
}
