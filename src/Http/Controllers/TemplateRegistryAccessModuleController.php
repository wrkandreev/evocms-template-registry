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

        return \view('template-registry::admin.access', [
            'enabled' => $enabled,
            'apiPrefix' => '/' . trim((string) ($api['prefix'] ?? 'api/template-registry'), '/'),
            'toggleUrl' => '/' . trim((string) ($api['admin_prefix'] ?? 'manager/template-registry'), '/') . '/access/toggle',
        ]);
    }

    public function toggle(Request $request)
    {
        $store = new ApiAccessStateStore();

        $enabled = (bool) $request->query('enabled', false);
        $store->setEnabled($enabled);

        return \redirect('/' . trim((string) \config('template-registry.api.admin_prefix', 'manager/template-registry'), '/') . '/access');
    }
}
