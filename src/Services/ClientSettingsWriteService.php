<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ClientSettingsWriteService
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateValues(array $input): array
    {
        $values = isset($input['values']) && is_array($input['values']) ? $input['values'] : $input;
        if (!is_array($values) || $values === []) {
            throw new RuntimeException('No client settings values provided for update.');
        }

        $schemaService = new ClientSettingsSchemaService($this->config, $this->projectRootPath());
        $schema = $schemaService->extract();
        if (empty($schema['exists'])) {
            throw new RuntimeException('ClientSettings config not found.');
        }

        $fieldsByName = $schemaService->fieldsByName($schema);
        $fields = [];
        foreach ($values as $fieldName => $value) {
            if (!is_string($fieldName) && !is_int($fieldName)) {
                continue;
            }

            $normalizedFieldName = trim((string) $fieldName);
            if ($normalizedFieldName === '') {
                continue;
            }

            if (!isset($fieldsByName[$normalizedFieldName])) {
                throw new RuntimeException('ClientSettings field not found.');
            }

            $field = $fieldsByName[$normalizedFieldName];
            if (empty($field['writable'])) {
                throw new RuntimeException('ClientSettings field is not writable.');
            }

            $settingName = trim((string) ($field['setting_name'] ?? ''));
            if ($settingName === '') {
                throw new RuntimeException('ClientSettings setting name could not be resolved.');
            }

            $fields[$normalizedFieldName] = [
                $settingName,
                $this->normalizeValue((string) ($field['type'] ?? ''), $value),
            ];
        }

        if ($fields === []) {
            throw new RuntimeException('No client settings values provided for update.');
        }

        $table = $this->requireSystemSettingsTable();
        $eventFields = $fields;
        $modx = $this->modx();

        if ($modx !== null && method_exists($modx, 'invokeEvent')) {
            $modx->invokeEvent('OnBeforeClientSettingsSave', [
                'fields' => &$eventFields,
            ]);
        }

        DB::transaction(function () use ($table, $eventFields): void {
            foreach ($eventFields as $field) {
                if (!is_array($field) || count($field) < 2) {
                    continue;
                }

                $settingName = trim((string) ($field[0] ?? ''));
                if ($settingName === '') {
                    continue;
                }

                DB::table($table)->updateOrInsert(
                    ['setting_name' => $settingName],
                    ['setting_value' => (string) ($field[1] ?? '')]
                );
            }
        });

        if ($modx !== null && method_exists($modx, 'invokeEvent')) {
            $modx->invokeEvent('OnDocFormSave', [
                'mode' => 'upd',
                'id' => 0,
            ]);
            $modx->invokeEvent('OnClientSettingsSave', [
                'fields' => $eventFields,
            ]);
        }

        if ($modx !== null && method_exists($modx, 'clearCache')) {
            $modx->clearCache('full');
        }

        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'client_settings',
            'message' => 'ClientSettings values updated.',
            'updated_fields' => array_values(array_keys($fields)),
            'updated_setting_names' => array_values(array_map(static fn (array $field): string => (string) ($field[0] ?? ''), $eventFields)),
            'regenerated' => $regenerated,
        ];
    }

    private function normalizeValue(string $type, mixed $value): string
    {
        $normalizedType = strtolower(trim($type));

        if ($normalizedType === 'custom_tv:multitv') {
            if (is_string($value)) {
                $decoded = json_decode($value);
                if (is_object($decoded) && isset($decoded->fieldValue)) {
                    $encoded = json_encode($decoded->fieldValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    return is_string($encoded) ? $encoded : '';
                }

                return $value;
            }

            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : '';
        }

        if ($normalizedType === 'checkbox') {
            if (is_bool($value)) {
                return $value ? '1' : '';
            }

            if (is_array($value)) {
                return implode('||', array_map(static fn (mixed $item): string => (string) $item, $value));
            }
        }

        if (is_array($value)) {
            return implode('||', array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        if (is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : '';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function requireSystemSettingsTable(): string
    {
        $table = $this->resolveSystemSettingsTableName();
        if ($table === null) {
            throw new RuntimeException('ClientSettings system settings table not found.');
        }

        return $table;
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

    private function regenerateRegistryIfNeeded(): bool
    {
        $api = (array) ($this->config['api'] ?? []);
        if (empty($api['regenerate_after_write'])) {
            return false;
        }

        $generator = new TemplateRegistryGenerator($this->config);
        $payload = $generator->buildPayload();
        $format = strtolower((string) ($this->config['format'] ?? 'all'));
        if (!in_array($format, ['json', 'md', 'php', 'all'], true)) {
            $format = 'all';
        }

        $generator->writePayload($payload, $this->resolveOutputPath(), $format);

        return true;
    }

    private function resolveOutputPath(): string
    {
        $output = trim((string) ($this->config['output'] ?? ''));
        if ($output !== '') {
            return $output;
        }

        $fallbacks = (array) ($this->config['output_fallbacks'] ?? []);
        if ($fallbacks === []) {
            $fallbacks = [
                'core/custom/packages/Main/generated/registry',
                'core/storage/app/template-registry/generated/registry',
            ];
        }

        foreach ($fallbacks as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $parent = $this->toAbsolutePath(dirname(trim($candidate)));
            if (is_dir($parent)) {
                return trim($candidate);
            }
        }

        return (string) reset($fallbacks);
    }

    private function toAbsolutePath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->projectRootPath($path);
    }

    private function projectRootPath(string $path = ''): string
    {
        $basePath = function_exists('base_path') ? base_path() : getcwd();
        $root = dirname((string) $basePath);

        if ($path === '') {
            return $root;
        }

        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }

    private function modx(): ?object
    {
        if (function_exists('evolutionCMS')) {
            try {
                $modx = \evolutionCMS();
                if (is_object($modx)) {
                    return $modx;
                }
            } catch (\Throwable $e) {
            }
        }

        if (function_exists('evo')) {
            try {
                $modx = \evo();
                if (is_object($modx)) {
                    return $modx;
                }
            } catch (\Throwable $e) {
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
