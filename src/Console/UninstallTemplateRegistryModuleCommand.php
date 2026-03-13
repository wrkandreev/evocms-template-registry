<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Console;

use EvolutionCMS\Models\SiteModule;
use Illuminate\Console\Command;

class UninstallTemplateRegistryModuleCommand extends Command
{
    protected $signature = 'template-registry:module:uninstall
        {--name= : Manager module name fallback match}';

    protected $description = 'Remove Evolution CMS manager module for Template Registry API access control';

    public function handle(): int
    {
        $name = trim((string) $this->option('name'));

        $query = SiteModule::query()->where('guid', 'template-registry-api-module');
        if ($name !== '') {
            $query->orWhere('name', $name);
        }

        $modules = $query->get();
        if ($modules->isEmpty()) {
            $this->info('Manager module not found. Nothing to uninstall.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($modules as $module) {
            $moduleName = (string) ($module->name ?? '');
            $moduleId = (int) ($module->id ?? 0);
            $module->delete();
            $this->info('Removed manager module: #' . $moduleId . ' ' . $moduleName);
            $count++;
        }

        $this->info('Total removed: ' . $count);
        return self::SUCCESS;
    }
}
