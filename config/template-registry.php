<?php

declare(strict_types=1);

return [
    'output' => '',
    'output_fallbacks' => [
        'core/custom/packages/Main/generated/registry',
        'core/storage/app/template-registry/generated/registry',
    ],
    'format' => 'all',
    'strict' => false,

    'project_fallback' => 'evolutioncms-project',

    'templates_table' => 'site_templates',
    'template_tv_pivot_table' => 'site_tmplvar_templates',
    'tvs_table' => 'site_tmplvars',
    'resources_table' => 'site_content',
    'tv_values_table' => 'site_tmplvar_contentvalues',

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
        'class_file' => 'assets/modules/clientsettings/core/src/ClientSettings.php',
        'settings_table' => 'system_settings',
        'setting_prefixes' => ['client_', 'default_', ''],
        'write_prefix' => '',
    ],

    'multitv' => [
        'customtv_file' => 'assets/tvs/multitv/multitv.customtv.php',
        'module_file' => 'assets/tvs/multitv/multitv.module.php',
        'configs_path' => 'assets/tvs/multitv/configs',
    ],

    'custom_tv_select' => [
        'customtv_file' => 'assets/tvs/selector/selector.customtv.php',
        'lib_path' => 'assets/tvs/selector/lib',
    ],

    'templatesedit' => [
        'plugin_path' => 'assets/plugins/templatesedit',
        'plugin_file' => 'assets/plugins/templatesedit/plugin.templatesedit.php',
        'class_file' => 'assets/plugins/templatesedit/class/templatesedit.class.php',
        'configs_path' => 'assets/plugins/templatesedit/configs',
    ],

    'pagebuilder' => [
        'plugin_path' => 'assets/plugins/pagebuilder',
        'main_file' => 'assets/plugins/pagebuilder/pagebuilder.php',
        'config_path' => 'assets/plugins/pagebuilder/config',
        'customtv_file' => 'assets/tvs/pagebuilder/pagebuilder.customtv.php',
    ],

    'blang' => [
        'module_path' => 'assets/modules/blang',
        'class_file' => 'assets/modules/blang/classes/bLang.php',
        'plugin_file' => 'assets/modules/blang/plugin.bLang.php',
        'snippet_file' => 'assets/modules/blang/snippet.bLang.php',
        'settings_table' => 'blang_settings',
        'fields_table' => 'blang_tmplvars',
        'template_links_table' => 'blang_tmplvar_templates',
        'lexicon_table' => 'blang',
    ],

    'migrations' => [
        'path' => 'core/custom/template-registry/migrations',
        'table' => 'template_registry_migrations',
    ],

    'api' => [
        'enabled' => true,
        'prefix' => 'api/template-registry',
        'middleware' => ['global'],
        'require_manager' => true,
        'access_token' => '',
        'write_enabled' => false,
        'write_access_token' => '',
        'regenerate_after_write' => true,
        'admin_prefix' => 'template-registry-admin',
    ],
];
