<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Support;

use EvolutionCMS\Models\SitePlugin;
use EvolutionCMS\Models\SitePluginEvent;
use EvolutionCMS\Models\SystemEventname;

class RegistryAutogeneratePluginManager
{
    public const GUID = 'template-registry-auto-generate-plugin';
    public const DEFAULT_NAME = 'Template Registry Auto Generate';
    public const DEFAULT_DESCRIPTION = 'Auto-generate template registry on TV/template save/delete';

    /** @return array<int,string> */
    public function eventNames(): array
    {
        return [
            'OnTVFormSave',
            'OnTVFormDelete',
            'OnTempFormSave',
            'OnTempFormDelete',
        ];
    }

    public function find(?string $fallbackName = null): ?SitePlugin
    {
        $query = SitePlugin::query()->where('moduleguid', self::GUID);
        $plugin = $query->first();
        if ($plugin !== null) {
            return $plugin;
        }

        $name = trim((string) $fallbackName);
        if ($name === '') {
            return SitePlugin::query()->where('name', self::DEFAULT_NAME)->first();
        }

        return SitePlugin::query()->where('name', $name)->first();
    }

    /**
     * @return array{plugin:SitePlugin,created:bool,missing_events:array<int,string>}
     */
    public function install(bool $enabled = false, ?string $name = null, ?string $description = null): array
    {
        $resolvedName = trim((string) $name);
        if ($resolvedName === '') {
            $resolvedName = self::DEFAULT_NAME;
        }

        $resolvedDescription = trim((string) $description);
        if ($resolvedDescription === '') {
            $resolvedDescription = self::DEFAULT_DESCRIPTION;
        }

        $plugin = $this->find($resolvedName);
        $created = false;
        if ($plugin === null) {
            $plugin = new SitePlugin();
            $created = true;
        }

        $plugin->fill([
            'name' => $resolvedName,
            'description' => $resolvedDescription,
            'editor_type' => 0,
            'category' => 0,
            'cache_type' => 0,
            'plugincode' => $this->pluginCode(),
            'locked' => 0,
            'properties' => '',
            'disabled' => $enabled ? 0 : 1,
            'moduleguid' => self::GUID,
        ]);
        $plugin->save();

        $sync = $this->syncEvents((int) $plugin->id);

        return [
            'plugin' => $plugin,
            'created' => $created,
            'missing_events' => $sync['missing_events'],
        ];
    }

    public function setEnabled(SitePlugin $plugin, bool $enabled): SitePlugin
    {
        $plugin->disabled = $enabled ? 0 : 1;
        $plugin->save();
        return $plugin;
    }

    public function uninstall(?string $fallbackName = null): int
    {
        $query = SitePlugin::query()->where('moduleguid', self::GUID);

        $name = trim((string) $fallbackName);
        if ($name !== '') {
            $query->orWhere('name', $name);
        }

        $plugins = $query->get();
        if ($plugins->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($plugins as $plugin) {
            $pluginId = (int) ($plugin->id ?? 0);
            if ($pluginId > 0) {
                SitePluginEvent::query()->where('pluginid', $pluginId)->delete();
            }
            $plugin->delete();
            $count++;
        }

        return $count;
    }

    /** @return array<string,mixed> */
    public function status(?string $fallbackName = null): array
    {
        $expected = $this->eventNames();
        $plugin = $this->find($fallbackName);
        if ($plugin === null) {
            return [
                'exists' => false,
                'enabled' => false,
                'id' => null,
                'name' => null,
                'events_expected' => $expected,
                'events_bound' => [],
                'events_unbound' => $expected,
                'events_missing_in_system' => $this->missingEventsInSystem($expected),
            ];
        }

        $pluginId = (int) ($plugin->id ?? 0);
        $bound = [];
        if ($pluginId > 0) {
            $bound = SitePluginEvent::query()
                ->join((new SystemEventname())->getTable() . ' as se', 'se.id', '=', 'evtid')
                ->where('pluginid', $pluginId)
                ->pluck('se.name')
                ->map(static fn($name) => (string) $name)
                ->all();
        }

        $bound = array_values(array_intersect($expected, $bound));
        $unbound = array_values(array_diff($expected, $bound));

        return [
            'exists' => true,
            'enabled' => ((int) ($plugin->disabled ?? 1)) === 0,
            'id' => $pluginId,
            'name' => (string) ($plugin->name ?? ''),
            'events_expected' => $expected,
            'events_bound' => $bound,
            'events_unbound' => $unbound,
            'events_missing_in_system' => $this->missingEventsInSystem($expected),
        ];
    }

    /**
     * @return array{missing_events:array<int,string>}
     */
    private function syncEvents(int $pluginId): array
    {
        $expected = $this->eventNames();
        $eventsMap = SystemEventname::query()
            ->whereIn('name', $expected)
            ->pluck('id', 'name')
            ->all();

        SitePluginEvent::query()->where('pluginid', $pluginId)->delete();

        foreach ($expected as $eventName) {
            if (!array_key_exists($eventName, $eventsMap)) {
                continue;
            }

            SitePluginEvent::query()->create([
                'pluginid' => $pluginId,
                'evtid' => (int) $eventsMap[$eventName],
                'priority' => 0,
            ]);
        }

        return [
            'missing_events' => array_values(array_diff($expected, array_keys($eventsMap))),
        ];
    }

    /**
     * @param array<int,string> $expected
     * @return array<int,string>
     */
    private function missingEventsInSystem(array $expected): array
    {
        $eventsMap = SystemEventname::query()
            ->whereIn('name', $expected)
            ->pluck('id', 'name')
            ->all();

        return array_values(array_diff($expected, array_keys($eventsMap)));
    }

    private function pluginCode(): string
    {
        return <<<'PHP'
$handledEvents = ['OnTVFormSave', 'OnTVFormDelete', 'OnTempFormSave', 'OnTempFormDelete'];
$currentEvent = isset($modx->event->name) ? (string) $modx->event->name : '';
if (!in_array($currentEvent, $handledEvents, true)) {
    return;
}

try {
    $config = function_exists('config') ? (array) config('template-registry', []) : [];

    $generator = new \WrkAndreev\EvocmsTemplateRegistry\Services\TemplateRegistryGenerator($config);
    $payload = $generator->buildPayload();

    $output = trim((string) ($config['output'] ?? ''));
    if ($output === '') {
        $output = 'core/custom/packages/Main/generated/registry';
    }

    $format = strtolower((string) ($config['format'] ?? 'all'));
    if (!in_array($format, ['json', 'md', 'php', 'all'], true)) {
        $format = 'all';
    }

    $generator->writePayload($payload, $output, $format);
} catch (\Throwable $e) {
    if (isset($modx) && is_object($modx) && method_exists($modx, 'logEvent')) {
        $modx->logEvent(0, 3, 'Template Registry plugin error: ' . $e->getMessage(), 'Template Registry');
    }
}
PHP;
    }
}
