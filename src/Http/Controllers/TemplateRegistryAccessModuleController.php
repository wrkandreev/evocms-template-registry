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
        $configuredToken = trim((string) ($api['access_token'] ?? ''));
        $token = $store->getAccessToken($configuredToken);
        $tokenSource = $store->hasTokenOverride() ? 'runtime' : 'config';
        $adminPrefix = trim((string) ($api['admin_prefix'] ?? 'template-registry-admin'), '/');

        return \view('template-registry::admin.access', [
            'enabled' => $enabled,
            'apiPrefix' => '/' . trim((string) ($api['prefix'] ?? 'api/template-registry'), '/'),
            'token' => $token,
            'tokenSource' => $tokenSource,
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
        $store = new ApiAccessStateStore();
        if ((bool) $request->input('reset_to_config', false)) {
            $store->clearAccessTokenOverride();

            return \redirect('/' . trim((string) \config('template-registry.api.admin_prefix', 'template-registry-admin'), '/') . '/access')
                ->with('status', 'Runtime token override cleared.');
        }

        $token = trim((string) $request->input('access_token', ''));
        if (strlen($token) > 512) {
            $token = substr($token, 0, 512);
        }

        $store->setAccessToken($token);

        return \redirect('/' . trim((string) \config('template-registry.api.admin_prefix', 'template-registry-admin'), '/') . '/access')
            ->with('status', 'Access token updated.');
    }
}
