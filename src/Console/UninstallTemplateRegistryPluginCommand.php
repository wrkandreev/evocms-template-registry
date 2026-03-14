<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Console;

use Illuminate\Console\Command;
use WrkAndreev\EvocmsTemplateRegistry\Support\RegistryAutogeneratePluginManager;

class UninstallTemplateRegistryPluginCommand extends Command
{
    protected $signature = 'template-registry:plugin:uninstall
        {--name= : Plugin name fallback match}';

    protected $description = 'Remove Evolution CMS plugin for auto-regeneration on TV/template changes';

    public function handle(): int
    {
        $manager = new RegistryAutogeneratePluginManager();
        $name = trim((string) $this->option('name'));

        $removed = $manager->uninstall($name);
        if ($removed === 0) {
            $this->info('Plugin not found. Nothing to uninstall.');
            return self::SUCCESS;
        }

        $this->info('Removed plugins: ' . $removed);
        return self::SUCCESS;
    }
}
