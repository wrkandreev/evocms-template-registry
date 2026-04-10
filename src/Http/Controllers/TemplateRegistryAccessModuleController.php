<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Http\Controllers;

use Illuminate\Http\Request;
use WrkAndreev\EvocmsTemplateRegistry\Support\ModulePreviewBuilder;
use WrkAndreev\EvocmsTemplateRegistry\Support\ModuleSettingsManager;
use WrkAndreev\EvocmsTemplateRegistry\Support\RegistryAutogeneratePluginManager;

class TemplateRegistryAccessModuleController
{
    public function index(Request $request)
    {
        $config = (array) \config('template-registry', []);
        $api = (array) ($config['api'] ?? []);

        $settingsManager = new ModuleSettingsManager();
        $enabled = $settingsManager->isApiEnabled($api);
        $token = $settingsManager->readToken($api);
        $writeEnabled = $settingsManager->isWriteEnabled($api);
        $writeToken = $settingsManager->readWriteToken($api);
        $adminPrefix = trim((string) ($api['admin_prefix'] ?? 'template-registry-admin'), '/');
        $activeTab = (string) $request->query('tab', 'access');
        if (!in_array($activeTab, ['access', 'preview'], true)) {
            $activeTab = 'access';
        }

        $preview = null;
        $previewError = null;
        if ($activeTab === 'preview') {
            $previewData = (new ModulePreviewBuilder())->build($config);
            $preview = $previewData['preview'];
            $previewError = $previewData['error'];
        }

        $pluginManager = new RegistryAutogeneratePluginManager();
        $pluginStatus = $pluginManager->status();

        $accessUrl = '/' . $adminPrefix . '/access';

        return \view('template-registry::admin.access', [
            'enabled' => $enabled,
            'apiPrefix' => '/' . trim((string) ($api['prefix'] ?? 'api/template-registry'), '/'),
            'token' => $token,
            'writeEnabled' => $writeEnabled,
            'writeToken' => $writeToken,
            'settingsUrl' => '/' . $adminPrefix . '/access/settings',
            'accessTabUrl' => $accessUrl . '?tab=access',
            'previewTabUrl' => $accessUrl . '?tab=preview',
            'activeTab' => $activeTab,
            'preview' => $preview,
            'previewError' => $previewError,
            'pluginStatus' => $pluginStatus,
        ]);
    }

    public function saveSettings(Request $request)
    {
        $result = (new ModuleSettingsManager())->save(
            (string) $request->input('api_enabled', 'enabled'),
            (string) $request->input('access_token', ''),
            (string) $request->input('write_enabled', 'disabled'),
            (string) $request->input('write_access_token', ''),
            (string) $request->input('plugin_state', 'disabled')
        );

        if ($result['success'] !== true) {
            return $this->accessTabRedirect()
                ->with('statusError', 'Failed to update custom/config/template-registry.php');
        }

        $redirect = $this->accessTabRedirect()->with('status', 'Settings saved.');
        if (is_string($result['warning']) && $result['warning'] !== '') {
            $redirect = $redirect->with('statusWarning', $result['warning']);
        }

        return $redirect;
    }

    private function accessTabRedirect()
    {
        $prefix = trim((string) \config('template-registry.api.admin_prefix', 'template-registry-admin'), '/');
        return \redirect('/' . $prefix . '/access?tab=access');
    }
}
