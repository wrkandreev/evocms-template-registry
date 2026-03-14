<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientSettingsExtractor
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
        $configDir = $this->absolutePath((string) $this->cfg('client_settings.config_path', 'assets/modules/clientsettings/config'));
        $selectorDir = $this->absolutePath((string) $this->cfg('client_settings.selector_controllers_path', 'assets/tvs/selector/lib'));

        $selectorMap = $this->buildSelectorControllerMap($selectorDir);
        $selectorDirExists = is_dir($selectorDir);
        $configDirExists = is_dir($configDir);

        $result = [
            'exists' => $configDirExists,
            'tabs' => [],
            'fields_catalog' => [],
            'stats' => [
                'tabs_total' => 0,
                'tabs_valid' => 0,
                'tabs_invalid' => 0,
                'fields_total' => 0,
                'fields_with_values' => 0,
                'selector_fields_total' => 0,
                'selector_controllers_found' => 0,
                'selector_controllers_missing' => 0,
                'selector_controllers_dir_exists' => $selectorDirExists,
                'values_table_exists' => false,
            ],
        ];

        if (!$configDirExists) {
            return $result;
        }

        $files = glob($configDir . '/*.php') ?: [];
        sort($files);
        $result['stats']['tabs_total'] = count($files);

        foreach ($files as $filePath) {
            $tab = $this->extractTabFromFile($filePath, $selectorMap, (array) $result['stats']);
            $result['stats'] = $tab['stats'];

            if (!$tab['valid']) {
                $result['tabs'][] = [
                    'id' => $tab['id'],
                    'name' => $tab['name'],
                    'source_file' => $tab['source_file'],
                    'valid' => false,
                    'fields' => [],
                    'error' => $tab['error'],
                ];
                continue;
            }

            $result['tabs'][] = [
                'id' => $tab['id'],
                'name' => $tab['name'],
                'source_file' => $tab['source_file'],
                'valid' => true,
                'fields' => $tab['fields'],
            ];

            foreach ($tab['fields'] as $field) {
                $result['fields_catalog'][] = $field;
            }
        }

        $this->enrichWithFieldValues($result);

        return $result;
    }

    /** @param array<string,array<string,mixed>> $selectorMap @param array<string,mixed> $stats @return array<string,mixed> */
    private function extractTabFromFile(string $filePath, array $selectorMap, array $stats): array
    {
        $id = pathinfo($filePath, PATHINFO_FILENAME);
        $sourceFile = $this->toProjectRelativePath($filePath);

        try {
            $raw = $this->loadPhpArray($filePath);
        } catch (\Throwable $e) {
            $stats['tabs_invalid'] = (int) ($stats['tabs_invalid'] ?? 0) + 1;
            return [
                'id' => $id,
                'name' => $id,
                'source_file' => $sourceFile,
                'valid' => false,
                'fields' => [],
                'error' => 'Failed to load tab config: ' . $e->getMessage(),
                'stats' => $stats,
            ];
        }

        if (!is_array($raw)) {
            $stats['tabs_invalid'] = (int) ($stats['tabs_invalid'] ?? 0) + 1;
            return [
                'id' => $id,
                'name' => $id,
                'source_file' => $sourceFile,
                'valid' => false,
                'fields' => [],
                'error' => 'Tab config must return array.',
                'stats' => $stats,
            ];
        }

        $fieldsRaw = $this->extractFieldsCollection($raw);
        if (!is_array($fieldsRaw)) {
            $fieldsRaw = [];
        }

        $fields = [];
        $fieldIndex = 0;
        foreach ($fieldsRaw as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $fields[] = $this->normalizeField($value, $id, (string) $key, $fieldIndex, $selectorMap, $stats);
            $fieldIndex++;
        }

        $stats['tabs_valid'] = (int) ($stats['tabs_valid'] ?? 0) + 1;

        return [
            'id' => $id,
            'name' => $this->resolveTabName($id, $raw),
            'source_file' => $sourceFile,
            'valid' => true,
            'fields' => $fields,
            'error' => null,
            'stats' => $stats,
        ];
    }

    /** @param array<string,mixed> $raw @return array<int|string,mixed> */
    private function extractFieldsCollection(array $raw): array
    {
        if (isset($raw['settings']) && is_array($raw['settings'])) {
            return $raw['settings'];
        }

        if (isset($raw['fields']) && is_array($raw['fields'])) {
            return $raw['fields'];
        }

        if ($this->isList($raw)) {
            return $raw;
        }

        return [];
    }

    /** @param array<string,mixed> $field @param array<string,array<string,mixed>> $selectorMap @param array<string,mixed> &$stats @return array<string,mixed> */
    private function normalizeField(array $field, string $tabId, string $key, int $index, array $selectorMap, array &$stats): array
    {
        $name = trim((string) ($field['name'] ?? $field['field'] ?? $field['id'] ?? $key));
        if ($name === '') {
            $name = 'field_' . ($index + 1);
        }

        $type = trim((string) ($field['type'] ?? $field['input'] ?? $field['tv_type'] ?? ''));

        $item = [
            'tab_id' => $tabId,
            'name' => $name,
            'caption' => (string) ($field['caption'] ?? $field['title'] ?? $field['label'] ?? $name),
            'type' => $type,
            'required' => (bool) ($field['required'] ?? false),
            'setting_name' => null,
            'value' => null,
        ];

        $stats['fields_total'] = (int) ($stats['fields_total'] ?? 0) + 1;

        if (strtolower($type) === 'customtv:selector') {
            $stats['selector_fields_total'] = (int) ($stats['selector_fields_total'] ?? 0) + 1;
            $selector = $this->resolveSelectorController($field, $selectorMap);
            if ((bool) $selector['controller_exists']) {
                $stats['selector_controllers_found'] = (int) ($stats['selector_controllers_found'] ?? 0) + 1;
            } else {
                $stats['selector_controllers_missing'] = (int) ($stats['selector_controllers_missing'] ?? 0) + 1;
            }
            $item['selector'] = $selector;
        }

        return $item;
    }

    /** @param array<string,mixed> &$result */
    private function enrichWithFieldValues(array &$result): void
    {
        $fieldNames = [];
        foreach ((array) ($result['fields_catalog'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name !== '') {
                $fieldNames[$name] = true;
            }
        }

        if ($fieldNames === []) {
            $result['stats']['values_table_exists'] = $this->hasSystemSettingsTable();
            return;
        }

        $valuesByField = $this->loadValuesByFieldNames(array_keys($fieldNames));
        $result['stats']['values_table_exists'] = (bool) ($valuesByField['_table_exists'] ?? false);
        unset($valuesByField['_table_exists']);

        foreach ($result['tabs'] as &$tab) {
            if (!is_array($tab) || !isset($tab['fields']) || !is_array($tab['fields'])) {
                continue;
            }

            foreach ($tab['fields'] as &$field) {
                if (!is_array($field)) {
                    continue;
                }
                $this->applyFieldValue($field, $valuesByField);
            }
            unset($field);
        }
        unset($tab);

        foreach ($result['fields_catalog'] as &$field) {
            if (!is_array($field)) {
                continue;
            }
            $this->applyFieldValue($field, $valuesByField);
        }
        unset($field);

        $withValues = 0;
        foreach ((array) ($result['fields_catalog'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }
            if (!empty($field['setting_name'])) {
                $withValues++;
            }
        }
        $result['stats']['fields_with_values'] = $withValues;
    }

    /** @param array<string,mixed> &$field @param array<string,array<string,mixed>> $valuesByField */
    private function applyFieldValue(array &$field, array $valuesByField): void
    {
        $name = trim((string) ($field['name'] ?? ''));
        if ($name === '' || !isset($valuesByField[$name]) || !is_array($valuesByField[$name])) {
            return;
        }

        $match = $valuesByField[$name];
        $field['setting_name'] = (string) ($match['setting_name'] ?? '');
        $field['value'] = (string) ($match['value'] ?? '');
    }

    /** @param array<int,string> $fieldNames @return array<string,mixed> */
    private function loadValuesByFieldNames(array $fieldNames): array
    {
        $table = $this->resolveSystemSettingsTableName();
        if ($table === null) {
            return ['_table_exists' => false];
        }

        $prefixes = (array) $this->cfg('client_settings.setting_prefixes', ['client_', 'default_', '']);
        $normalizedPrefixes = [];
        foreach ($prefixes as $prefix) {
            if (!is_string($prefix)) {
                continue;
            }
            $normalizedPrefixes[] = $prefix;
        }
        if ($normalizedPrefixes === []) {
            $normalizedPrefixes = ['client_', 'default_', ''];
        }

        $candidateKeys = [];
        foreach ($fieldNames as $fieldName) {
            foreach ($normalizedPrefixes as $prefix) {
                $candidateKeys[] = $prefix . $fieldName;
            }
        }
        $candidateKeys = array_values(array_unique($candidateKeys));

        if ($candidateKeys === []) {
            return ['_table_exists' => true];
        }

        try {
            $rows = DB::table($table)
                ->select(['setting_name', 'setting_value'])
                ->whereIn('setting_name', $candidateKeys)
                ->get();
        } catch (\Throwable $e) {
            return ['_table_exists' => true];
        }

        $bySettingName = [];
        foreach ($rows as $row) {
            $key = (string) ($row->setting_name ?? '');
            if ($key === '') {
                continue;
            }
            $bySettingName[$key] = (string) ($row->setting_value ?? '');
        }

        $result = ['_table_exists' => true];
        foreach ($fieldNames as $fieldName) {
            foreach ($normalizedPrefixes as $prefix) {
                $settingName = $prefix . $fieldName;
                if (!array_key_exists($settingName, $bySettingName)) {
                    continue;
                }

                $result[$fieldName] = [
                    'setting_name' => $settingName,
                    'value' => $bySettingName[$settingName],
                ];
                break;
            }
        }

        return $result;
    }

    private function hasSystemSettingsTable(): bool
    {
        return $this->resolveSystemSettingsTableName() !== null;
    }

    private function resolveSystemSettingsTableName(): ?string
    {
        $base = (string) $this->cfg('client_settings.settings_table', 'system_settings');
        if ($base === '') {
            return null;
        }

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

    /** @param array<string,mixed> $field @param array<string,array<string,mixed>> $selectorMap @return array<string,mixed> */
    private function resolveSelectorController(array $field, array $selectorMap): array
    {
        $options = isset($field['options']) && is_array($field['options'])
            ? $field['options']
            : [];

        $controllerName = trim((string) (
            $field['controller']
            ?? $field['selector_controller']
            ?? ($options['controller'] ?? null)
            ?? ''
        ));

        $normalized = $this->normalizeControllerKey($controllerName);
        $matched = $normalized !== '' && array_key_exists($normalized, $selectorMap)
            ? $selectorMap[$normalized]
            : null;

        return [
            'controller' => $controllerName !== '' ? $controllerName : null,
            'controller_path' => $matched['path'] ?? null,
            'controller_exists' => $matched !== null,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function buildSelectorControllerMap(string $selectorDir): array
    {
        if (!is_dir($selectorDir)) {
            return [];
        }

        $files = glob($selectorDir . '/*.controller.class.php') ?: [];
        $map = [];

        foreach ($files as $filePath) {
            $file = basename($filePath);
            $base = (string) preg_replace('/\.controller\.class\.php$/i', '', $file);
            $key = $this->normalizeControllerKey($base);
            if ($key === '') {
                continue;
            }

            $map[$key] = [
                'name' => $base,
                'path' => $this->toProjectRelativePath($filePath),
            ];
        }

        return $map;
    }

    private function normalizeControllerKey(string $controller): string
    {
        $normalized = strtolower(trim($controller));
        $normalized = preg_replace('/\.controller\.class\.php$/i', '', $normalized) ?: $normalized;
        $normalized = preg_replace('/\.class\.php$/i', '', $normalized) ?: $normalized;
        $normalized = preg_replace('/\.php$/i', '', $normalized) ?: $normalized;
        return $normalized;
    }

    /** @return mixed */
    private function loadPhpArray(string $filePath)
    {
        return (static function (string $__file) {
            return include $__file;
        })($filePath);
    }

    /** @param array<string,mixed> $raw */
    private function resolveTabName(string $id, array $raw): string
    {
        $name = (string) ($raw['caption'] ?? $raw['title'] ?? $raw['name'] ?? $raw['tab_name'] ?? $id);
        $name = trim($name);
        return $name !== '' ? $name : $id;
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

    private function toProjectRelativePath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', $this->projectRoot);
        $rootWithSlash = rtrim($root, '/') . '/';

        if (str_starts_with($normalizedPath, $rootWithSlash)) {
            return ltrim(substr($normalizedPath, strlen($rootWithSlash)), '/');
        }

        return $path;
    }

    private function isList(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
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
