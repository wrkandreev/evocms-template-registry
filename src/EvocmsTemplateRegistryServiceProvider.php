<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry;

use EvolutionCMS\ServiceProvider;
use WrkAndreev\EvocmsTemplateRegistry\Console\GenerateTemplateRegistryCommand;
use WrkAndreev\EvocmsTemplateRegistry\Console\InstallTemplateRegistryModuleCommand;
use WrkAndreev\EvocmsTemplateRegistry\Console\InstallTemplateRegistryPluginCommand;
use WrkAndreev\EvocmsTemplateRegistry\Console\UninstallTemplateRegistryModuleCommand;
use WrkAndreev\EvocmsTemplateRegistry\Console\UninstallTemplateRegistryPluginCommand;

class EvocmsTemplateRegistryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $packageConfigPath = dirname(__DIR__) . '/config/template-registry.php';
        $customConfigPath = $this->customConfigPath();

        if (method_exists($this, 'mergeConfigFrom')) {
            $this->mergeConfigFrom($packageConfigPath, 'template-registry');
        }

        if (is_file($customConfigPath)) {
            $customConfig = require $customConfigPath;
            if (is_array($customConfig)) {
                $current = (array) $this->app['config']->get('template-registry', []);
                $this->app['config']->set('template-registry', array_replace_recursive($current, $customConfig));
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTemplateRegistryCommand::class,
                InstallTemplateRegistryModuleCommand::class,
                UninstallTemplateRegistryModuleCommand::class,
                InstallTemplateRegistryPluginCommand::class,
                UninstallTemplateRegistryPluginCommand::class,
            ]);

            if (method_exists($this, 'publishes')) {
                $this->publishes([
                    $packageConfigPath => $customConfigPath,
                ], 'evocms-template-registry-config');
            }
        }
    }

    public function boot()
    {
        if (method_exists($this, 'loadRoutesFrom')) {
            $this->loadRoutesFrom(dirname(__DIR__) . '/routes.php');
        }

        if (method_exists($this, 'loadViewsFrom')) {
            $this->loadViewsFrom(dirname(__DIR__) . '/views', 'template-registry');
        }
    }

    private function customConfigPath(): string
    {
        if (function_exists('base_path')) {
            return rtrim((string) \base_path('custom/config/template-registry.php'), '/');
        }

        $basePath = getcwd() ?: '';
        return rtrim($basePath, '/') . '/custom/config/template-registry.php';
    }
}
