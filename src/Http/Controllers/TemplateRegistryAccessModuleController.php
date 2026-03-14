<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Http\Controllers;

use Illuminate\Http\Request;
use WrkAndreev\EvocmsTemplateRegistry\Support\ApiAccessStateStore;

class TemplateRegistryAccessModuleController
{
    public function index()
    {
        $config = (array) \config('template-registry', []);
        $api = (array) ($config['api'] ?? []);

        $store = new ApiAccessStateStore();
        $enabled = $store->isEnabled((bool) ($api['enabled'] ?? true));
        $token = trim((string) ($api['access_token'] ?? ''));
        $adminPrefix = trim((string) ($api['admin_prefix'] ?? 'template-registry-admin'), '/');

        return \view('template-registry::admin.access', [
            'enabled' => $enabled,
            'apiPrefix' => '/' . trim((string) ($api['prefix'] ?? 'api/template-registry'), '/'),
            'token' => $token,
            'toggleUrl' => '/' . $adminPrefix . '/access/toggle',
            'tokenUrl' => '/' . $adminPrefix . '/access/token',
        ]);
    }

    public function toggle(Request $request)
    {
        $store = new ApiAccessStateStore();

        $enabled = (bool) $request->query('enabled', false);
        $store->setEnabled($enabled);

        return \redirect('/' . trim((string) \config('template-registry.api.admin_prefix', 'template-registry-admin'), '/') . '/access');
    }

    public function updateToken(Request $request)
    {
        $token = trim((string) $request->input('access_token', ''));
        if (strlen($token) > 512) {
            $token = substr($token, 0, 512);
        }

        if (!$this->writeAccessTokenToConfig($token)) {
            return \redirect('/' . trim((string) \config('template-registry.api.admin_prefix', 'template-registry-admin'), '/') . '/access')
                ->with('statusError', 'Failed to update config/template-registry.php');
        }

        return \redirect('/' . trim((string) \config('template-registry.api.admin_prefix', 'template-registry-admin'), '/') . '/access')
            ->with('status', 'Access token updated in config.');
    }

    private function writeAccessTokenToConfig(string $token): bool
    {
        $path = $this->configFilePath();
        if (!is_file($path)) {
            $packageConfigPath = dirname(__DIR__, 3) . '/config/template-registry.php';
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
