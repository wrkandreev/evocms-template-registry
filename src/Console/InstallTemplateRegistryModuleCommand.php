<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Console;

use EvolutionCMS\Models\SiteModule;
use Illuminate\Console\Command;

class InstallTemplateRegistryModuleCommand extends Command
{
    protected $signature = 'template-registry:module:install
        {--name=Template Registry API : Manager module name}
        {--description=Toggle access to Template Registry API : Manager module description}
        {--disabled : Create module as disabled}';

    protected $description = 'Create or update Evolution CMS manager module for Template Registry API access control';

    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        if ($name === '') {
            $name = 'Template Registry API';
        }

        $description = trim((string) $this->option('description'));
        if ($description === '') {
            $description = 'Toggle access to Template Registry API';
        }

        $module = SiteModule::query()->where('name', $name)->first();

        $payload = [
            'name' => $name,
            'description' => $description,
            'editor_type' => 0,
            'disabled' => (bool) $this->option('disabled') ? 1 : 0,
            'category' => 0,
            'wrap' => 0,
            'locked' => 0,
            'icon' => 'fa fa-database',
            'enable_resource' => 0,
            'resourcefile' => '',
            'enable_sharedparams' => 0,
            'properties' => '',
            'guid' => 'template-registry-api-module',
            'modulecode' => $this->moduleCode(),
        ];

        if ($module) {
            $module->fill($payload);
            $module->save();
            $this->info('Updated manager module: #' . $module->id . ' ' . $module->name);
            return self::SUCCESS;
        }

        $module = SiteModule::query()->create($payload);
        $this->info('Created manager module: #' . $module->id . ' ' . $module->name);

        return self::SUCCESS;
    }

    private function moduleCode(): string
    {
        return <<<'PHP'
<?php
$prefix = trim((string) config('template-registry.api.admin_prefix', 'template-registry-admin'), '/');
$url = '/' . $prefix . '/access';

echo '<iframe src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:block;width:100%;height:calc(100vh - 24px);min-height:calc(100vh - 24px);border:0;background:#fff"></iframe>';
PHP;
    }
}
