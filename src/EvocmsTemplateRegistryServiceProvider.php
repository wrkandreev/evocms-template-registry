<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry;

use EvolutionCMS\ServiceProvider;
use WrkAndreev\EvocmsTemplateRegistry\Console\GenerateTemplateRegistryCommand;

class EvocmsTemplateRegistryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $configPath = dirname(__DIR__) . '/config/template-registry.php';

        if (method_exists($this, 'mergeConfigFrom')) {
            $this->mergeConfigFrom($configPath, 'template-registry');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTemplateRegistryCommand::class,
            ]);

            if (method_exists($this, 'publishes') && function_exists('config_path')) {
                $this->publishes([
                    $configPath => config_path('template-registry.php'),
                ], 'evocms-template-registry-config');
            }
        }
    }
}
