<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Console;

use Illuminate\Console\Command;
use WrkAndreev\EvocmsTemplateRegistry\Support\RegistryAutogeneratePluginManager;

class InstallTemplateRegistryPluginCommand extends Command
{
    protected $signature = 'template-registry:plugin:install
        {--name=Template Registry Auto Generate : Plugin name}
        {--description=Auto-generate template registry on TV/template save/delete : Plugin description}
        {--enabled : Install plugin as enabled}';

    protected $description = 'Create or update Evolution CMS plugin for auto-regeneration on TV/template changes';

    public function handle(): int
    {
        $manager = new RegistryAutogeneratePluginManager();

        $name = trim((string) $this->option('name'));
        $description = trim((string) $this->option('description'));
        $enabled = (bool) $this->option('enabled');

        $result = $manager->install($enabled, $name, $description);
        $plugin = $result['plugin'];
        $created = (bool) $result['created'];
        $missingEvents = (array) $result['missing_events'];

        $state = ((int) ($plugin->disabled ?? 1)) === 0 ? 'enabled' : 'disabled';
        $prefix = $created ? 'Created' : 'Updated';

        $this->info(sprintf('%s plugin: #%d %s (%s)', $prefix, (int) $plugin->id, (string) $plugin->name, $state));

        if ($missingEvents !== []) {
            $this->warn('System events not found: ' . implode(', ', $missingEvents));
        }

        return self::SUCCESS;
    }
}
