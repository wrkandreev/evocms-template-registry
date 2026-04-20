<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BLangExtractor
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
    public function extract(): array
    {
        $modulePath = $this->absolutePath((string) $this->cfg('blang.module_path', 'assets/modules/blang'));
        $settingsTable = $this->resolveTableName((string) $this->cfg('blang.settings_table', 'blang_settings'));
        $fieldsTable = $this->resolveTableName((string) $this->cfg('blang.fields_table', 'blang_tmplvars'));
        $templateLinksTable = $this->resolveTableName((string) $this->cfg('blang.template_links_table', 'blang_tmplvar_templates'));
        $lexiconTable = $this->resolveTableName((string) $this->cfg('blang.lexicon_table', 'blang'));

        $exists = is_dir($modulePath) || $settingsTable !== null || $fieldsTable !== null || $templateLinksTable !== null;

        $result = [
            'exists' => $exists,
            'languages' => [],
            'default_language' => '',
            'suffixes' => [],
            'settings' => [
                'auto_fields' => false,
                'auto_url' => false,
                'client_settings_prefix' => '',
                'menu_controller_fields' => [],
                'content_controller_fields' => [],
                'default_to_new_tab' => false,
                'pb_show_btn' => false,
                'pb_is_te3' => false,
                'pb_config' => '',
                'translate' => false,
                'translate_provider' => '',
            ],
            'fields_catalog' => [],
            'template_links' => [],
            'stats' => [
                'settings_table_exists' => $settingsTable !== null,
                'fields_table_exists' => $fieldsTable !== null,
                'template_links_table_exists' => $templateLinksTable !== null,
                'lexicon_table_exists' => $lexiconTable !== null,
                'languages_total' => 0,
                'fields_total' => 0,
                'template_links_total' => 0,
                'templates_total' => 0,
                'lexicon_entries_total' => 0,
            ],
        ];

        if ($settingsTable !== null) {
            $settingsMap = $this->loadSettingsMap($settingsTable);
            $languages = $this->parseListSetting((string) ($settingsMap['languages'] ?? ''));
            $suffixes = $this->parseMapSetting((string) ($settingsMap['suffixes'] ?? ''));

            $result['languages'] = $languages;
            $result['default_language'] = trim((string) ($settingsMap['default'] ?? ''));
            $result['suffixes'] = $this->normalizeSuffixes($languages, $suffixes);
            $result['settings'] = [
                'auto_fields' => $this->toBool($settingsMap['autoFields'] ?? null),
                'auto_url' => $this->toBool($settingsMap['autoUrl'] ?? null),
                'client_settings_prefix' => trim((string) ($settingsMap['clientSettingsPrefix'] ?? '')),
                'menu_controller_fields' => $this->parseCsvSetting((string) ($settingsMap['menu_controller_fields'] ?? '')),
                'content_controller_fields' => $this->parseCsvSetting((string) ($settingsMap['content_controller_fields'] ?? '')),
                'default_to_new_tab' => $this->toBool($settingsMap['default_to_new_tab'] ?? null),
                'pb_show_btn' => $this->toBool($settingsMap['pb_show_btn'] ?? null),
                'pb_is_te3' => $this->toBool($settingsMap['pb_is_te3'] ?? null),
                'pb_config' => trim((string) ($settingsMap['pb_config'] ?? '')),
                'translate' => $this->toBool($settingsMap['translate'] ?? null),
                'translate_provider' => trim((string) ($settingsMap['translate_provider'] ?? '')),
            ];
            $result['stats']['languages_total'] = count($languages);
        }

        if ($fieldsTable !== null) {
            $fields = $this->loadFieldsCatalog($fieldsTable, (array) $result['languages'], (array) $result['suffixes']);
            $result['fields_catalog'] = $fields;
            $result['stats']['fields_total'] = count($fields);
        }

        if ($templateLinksTable !== null) {
            $links = $this->loadTemplateLinks($templateLinksTable);
            $result['template_links'] = $links;
            $result['stats']['template_links_total'] = count($links);
            $result['stats']['templates_total'] = count(array_unique(array_map(static fn (array $link): int => (int) ($link['template_id'] ?? 0), $links)));
        }

        if ($lexiconTable !== null) {
            $result['stats']['lexicon_entries_total'] = (int) DB::table($lexiconTable)->count();
        }

        return $result;
    }

    /** @return array<string,string> */
    private function loadSettingsMap(string $settingsTable): array
    {
        $rows = DB::table($settingsTable)
            ->select(['name', 'value'])
            ->orderBy('name')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row->name ?? ''));
            if ($name === '') {
                continue;
            }

            $result[$name] = (string) ($row->value ?? '');
        }

        return $result;
    }

    /** @param array<int,string> $languages @param array<string,string> $suffixes @return array<int,array<string,mixed>> */
    private function loadFieldsCatalog(string $fieldsTable, array $languages, array $suffixes): array
    {
        $columns = [
            'id', 'type', 'name', 'caption', 'description', 'editor_type', 'category', 'locked', 'elements',
            'rank', 'display', 'display_params', 'default_text', 'multitv_translate_fields', 'tab',
        ];

        $availableColumns = array_values(array_filter($columns, static fn (string $column): bool => Schema::hasColumn($fieldsTable, $column)));
        $rows = DB::table($fieldsTable)
            ->select($availableColumns)
            ->orderBy('rank')
            ->orderBy('id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $baseName = trim((string) ($row->name ?? ''));
            if ($baseName === '') {
                continue;
            }

            $localizedNames = [];
            foreach ($languages as $language) {
                $localizedNames[$language] = $baseName . ($suffixes[$language] ?? '');
            }

            $result[] = [
                'id' => (int) ($row->id ?? 0),
                'name' => $baseName,
                'caption' => (string) ($row->caption ?? ''),
                'description' => (string) ($row->description ?? ''),
                'type' => (string) ($row->type ?? ''),
                'editor_type' => isset($row->editor_type) ? (int) $row->editor_type : 0,
                'category' => isset($row->category) ? (int) $row->category : 0,
                'locked' => !empty($row->locked),
                'elements' => (string) ($row->elements ?? ''),
                'rank' => isset($row->rank) ? (int) $row->rank : 0,
                'display' => (string) ($row->display ?? ''),
                'display_params' => (string) ($row->display_params ?? ''),
                'default_text' => (string) ($row->default_text ?? ''),
                'multitv_translate_fields' => $this->parseCsvSetting((string) ($row->multitv_translate_fields ?? '')),
                'tab' => (string) ($row->tab ?? ''),
                'localized_names' => $localizedNames,
            ];
        }

        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    private function loadTemplateLinks(string $templateLinksTable): array
    {
        $rows = DB::table($templateLinksTable)
            ->select(['tmplvarid', 'templateid', 'rank'])
            ->orderBy('templateid')
            ->orderBy('rank')
            ->orderBy('tmplvarid')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'field_id' => (int) ($row->tmplvarid ?? 0),
                'template_id' => (int) ($row->templateid ?? 0),
                'rank' => isset($row->rank) ? (int) $row->rank : 0,
            ];
        }

        return $result;
    }

    /** @return array<int,string> */
    private function parseListSetting(string $value): array
    {
        $parts = array_filter(array_map('trim', explode('||', $value)), static fn (string $item): bool => $item !== '');
        return array_values($parts);
    }

    /** @return array<int,string> */
    private function parseCsvSetting(string $value): array
    {
        $parts = array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== '');
        return array_values($parts);
    }

    /** @return array<string,string> */
    private function parseMapSetting(string $value): array
    {
        $result = [];
        foreach ($this->parseListSetting($value) as $item) {
            $parts = explode('==', $item, 2);
            $key = trim((string) ($parts[0] ?? ''));
            if ($key === '') {
                continue;
            }

            $result[$key] = (string) ($parts[1] ?? '');
        }

        return $result;
    }

    /** @param array<int,string> $languages @param array<string,string> $suffixes @return array<string,string> */
    private function normalizeSuffixes(array $languages, array $suffixes): array
    {
        $result = [];
        foreach ($languages as $language) {
            $result[$language] = (string) ($suffixes[$language] ?? '');
        }

        return $result;
    }

    private function toBool(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
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

    private function resolveTableName(string $base): ?string
    {
        if (Schema::hasTable($base)) {
            return $base;
        }

        $defaultConnection = (string) \config('database.default');
        $prefix = (string) \config("database.connections.{$defaultConnection}.prefix", '');
        if ($prefix !== '') {
            $withPrefix = $prefix . $base;
            if (Schema::hasTable($withPrefix)) {
                return $withPrefix;
            }
        }

        return null;
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
