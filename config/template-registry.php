<?php

declare(strict_types=1);

return [
    'output' => 'core/custom/packages/Main/generated/registry',
    'format' => 'all',
    'strict' => false,

    'project_fallback' => 'evolutioncms-project',

    'templates_table' => 'site_templates',
    'template_tv_pivot_table' => 'site_tmplvar_templates',
    'tvs_table' => 'site_tmplvars',

    'template_view_columns' => [
        'templateview',
        'view',
        'template_view',
    ],

    'fallback_controller_namespace' => 'EvolutionCMS\\Main\\Controllers',
    'fallback_view_pattern' => 'views/{alias}.blade.php',
    'normalize_alias_dashes' => true,

    'controller_prefix_map' => [
        'Main\\' => 'EvolutionCMS\\',
    ],

    'controller_namespace_paths' => [
        'EvolutionCMS\\Main\\' => 'core/custom/packages/Main/src/',
    ],

    'client_settings' => [
        'config_path' => 'assets/modules/clientsettings/config',
        'selector_controllers_path' => 'assets/tvs/selector/lib',
    ],

    'api' => [
        'enabled' => true,
        'prefix' => 'api/template-registry',
        'middleware' => ['web'],
        'require_manager' => true,
        'access_token' => '',
        'admin_prefix' => 'manager/template-registry',
    ],
];
