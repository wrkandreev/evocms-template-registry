<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

class SystemFeaturesDetector
{
    /** @var array<string,mixed> */
    private array $config;

    private string $projectRoot;

    /** @param array<string,mixed> $config */
    public function __construct(array $config, string $projectRoot)
    {
        $this->config = $config;
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    /** @return array<string,mixed> */
    public function detect(): array
    {
        return [
            'client_settings' => $this->detectClientSettings(),
            'multitv' => $this->detectMultiTv(),
            'custom_tv_select' => $this->detectCustomTvSelect(),
            'templatesedit' => $this->detectTemplatesEdit(),
            'pagebuilder' => $this->detectPageBuilder(),
            'simplegallery' => $this->detectSimpleGallery(),
            'blang' => $this->detectBLang(),
        ];
    }

    /** @return array<string,mixed> */
    private function detectClientSettings(): array
    {
        $configDir = $this->absolutePath((string) $this->cfg('client_settings.config_path', 'assets/modules/clientsettings/config'));
        $classFile = $this->absolutePath((string) $this->cfg('client_settings.class_file', 'assets/modules/clientsettings/core/src/ClientSettings.php'));

        $configDirExists = is_dir($configDir);
        $classFileExists = is_file($classFile);

        return [
            'installed' => $configDirExists || $classFileExists,
            'details' => [
                'config_dir_exists' => $configDirExists,
                'core_class_exists' => $classFileExists,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function detectMultiTv(): array
    {
        $customTvFile = $this->absolutePath((string) $this->cfg('multitv.customtv_file', 'assets/tvs/multitv/multitv.customtv.php'));
        $moduleFile = $this->absolutePath((string) $this->cfg('multitv.module_file', 'assets/tvs/multitv/multitv.module.php'));
        $configsDir = $this->absolutePath((string) $this->cfg('multitv.configs_path', 'assets/tvs/multitv/configs'));

        $configsCount = 0;
        if (is_dir($configsDir)) {
            $configs = glob($configsDir . '/*.config.inc.php') ?: [];
            $configsCount = count($configs);
        }

        $customTvFileExists = is_file($customTvFile);
        $moduleFileExists = is_file($moduleFile);
        $configsDirExists = is_dir($configsDir);

        return [
            'installed' => $customTvFileExists || $moduleFileExists || $configsDirExists,
            'details' => [
                'customtv_file_exists' => $customTvFileExists,
                'module_file_exists' => $moduleFileExists,
                'configs_dir_exists' => $configsDirExists,
                'configs_count' => $configsCount,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function detectCustomTvSelect(): array
    {
        $customTvFile = $this->absolutePath((string) $this->cfg('custom_tv_select.customtv_file', 'assets/tvs/selector/selector.customtv.php'));
        $libDir = $this->absolutePath((string) $this->cfg('custom_tv_select.lib_path', 'assets/tvs/selector/lib'));

        $controllers = is_dir($libDir)
            ? (glob($libDir . '/*.controller.class.php') ?: [])
            : [];

        $customTvFileExists = is_file($customTvFile);
        $libDirExists = is_dir($libDir);

        return [
            'installed' => $customTvFileExists || $libDirExists,
            'details' => [
                'customtv_file_exists' => $customTvFileExists,
                'lib_dir_exists' => $libDirExists,
                'controllers_count' => count($controllers),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function detectTemplatesEdit(): array
    {
        $pluginDir = $this->absolutePath((string) $this->cfg('templatesedit.plugin_path', 'assets/plugins/templatesedit'));
        $pluginFile = $this->absolutePath((string) $this->cfg('templatesedit.plugin_file', 'assets/plugins/templatesedit/plugin.templatesedit.php'));
        $classFile = $this->absolutePath((string) $this->cfg('templatesedit.class_file', 'assets/plugins/templatesedit/class/templatesedit.class.php'));
        $configsDir = $this->absolutePath((string) $this->cfg('templatesedit.configs_path', 'assets/plugins/templatesedit/configs'));

        $pluginDirExists = is_dir($pluginDir);
        $pluginFileExists = is_file($pluginFile);
        $classFileExists = is_file($classFile);
        $configsDirExists = is_dir($configsDir);

        return [
            'installed' => $pluginDirExists || $pluginFileExists || $classFileExists || $configsDirExists,
            'details' => [
                'plugin_dir_exists' => $pluginDirExists,
                'plugin_file_exists' => $pluginFileExists,
                'class_file_exists' => $classFileExists,
                'configs_dir_exists' => $configsDirExists,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function detectPageBuilder(): array
    {
        $pluginDir = $this->absolutePath((string) $this->cfg('pagebuilder.plugin_path', 'assets/plugins/pagebuilder'));
        $mainFile = $this->absolutePath((string) $this->cfg('pagebuilder.main_file', 'assets/plugins/pagebuilder/pagebuilder.php'));
        $configDir = $this->absolutePath((string) $this->cfg('pagebuilder.config_path', 'assets/plugins/pagebuilder/config'));
        $customTvFile = $this->absolutePath((string) $this->cfg('pagebuilder.customtv_file', 'assets/tvs/pagebuilder/pagebuilder.customtv.php'));

        $configCount = 0;
        if (is_dir($configDir)) {
            $configCount = count(array_merge(
                glob($configDir . '/*.php') ?: [],
                glob($configDir . '/*.php.sample') ?: []
            ));
        }

        $pluginDirExists = is_dir($pluginDir);
        $mainFileExists = is_file($mainFile);
        $configDirExists = is_dir($configDir);
        $customTvFileExists = is_file($customTvFile);

        return [
            'installed' => $pluginDirExists || $mainFileExists || $configDirExists || $customTvFileExists,
            'details' => [
                'plugin_dir_exists' => $pluginDirExists,
                'main_file_exists' => $mainFileExists,
                'config_dir_exists' => $configDirExists,
                'customtv_file_exists' => $customTvFileExists,
                'configs_count' => $configCount,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function detectSimpleGallery(): array
    {
        $pluginDir = $this->absolutePath((string) $this->cfg('simplegallery.plugin_path', 'assets/plugins/simplegallery'));
        $pluginFile = $this->absolutePath((string) $this->cfg('simplegallery.plugin_file', 'assets/plugins/simplegallery/plugin.simplegallery.php'));
        $thumbPluginFile = $this->absolutePath((string) $this->cfg('simplegallery.thumb_plugin_file', 'assets/plugins/simplegallery/plugin.sgthumb.php'));
        $snippetsDir = $this->absolutePath((string) $this->cfg('simplegallery.snippets_path', 'assets/snippets/simplegallery'));

        $snippetFiles = is_dir($snippetsDir)
            ? array_merge(
                glob($snippetsDir . '/*.php') ?: [],
                glob($snippetsDir . '/*.inc.php') ?: []
            )
            : [];

        $pluginDirExists = is_dir($pluginDir);
        $pluginFileExists = is_file($pluginFile);
        $thumbPluginFileExists = is_file($thumbPluginFile);
        $snippetsDirExists = is_dir($snippetsDir);

        return [
            'installed' => $pluginDirExists || $pluginFileExists || $thumbPluginFileExists || $snippetsDirExists,
            'details' => [
                'plugin_dir_exists' => $pluginDirExists,
                'plugin_file_exists' => $pluginFileExists,
                'thumb_plugin_file_exists' => $thumbPluginFileExists,
                'snippets_dir_exists' => $snippetsDirExists,
                'snippets_count' => count($snippetFiles),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function detectBLang(): array
    {
        $modulePath = $this->absolutePath((string) $this->cfg('blang.module_path', 'assets/modules/blang'));
        $classFile = $this->absolutePath((string) $this->cfg('blang.class_file', 'assets/modules/blang/classes/bLang.php'));
        $pluginFile = $this->absolutePath((string) $this->cfg('blang.plugin_file', 'assets/modules/blang/plugin.bLang.php'));
        $snippetFile = $this->absolutePath((string) $this->cfg('blang.snippet_file', 'assets/modules/blang/snippet.bLang.php'));

        $modulePathExists = is_dir($modulePath);
        $classFileExists = is_file($classFile);
        $pluginFileExists = is_file($pluginFile);
        $snippetFileExists = is_file($snippetFile);

        return [
            'installed' => $modulePathExists || $classFileExists || $pluginFileExists || $snippetFileExists,
            'details' => [
                'module_path_exists' => $modulePathExists,
                'class_file_exists' => $classFileExists,
                'plugin_file_exists' => $pluginFileExists,
                'snippet_file_exists' => $snippetFileExists,
            ],
        ];
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            return $this->projectRoot;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->projectRoot . '/' . ltrim($path, '/');
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        $current = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}
