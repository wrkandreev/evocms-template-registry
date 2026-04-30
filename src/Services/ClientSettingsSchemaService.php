<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

class ClientSettingsSchemaService
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
        $schema = (new ClientSettingsExtractor($this->config, $this->projectRoot))->extract();

        $duplicates = $this->duplicateNames($schema);
        $resolvedPrefix = $this->resolvePrefix($schema);

        foreach ($schema['tabs'] as &$tab) {
            if (!is_array($tab) || !isset($tab['fields']) || !is_array($tab['fields'])) {
                continue;
            }

            foreach ($tab['fields'] as &$field) {
                if (!is_array($field)) {
                    continue;
                }

                $field = $this->enrichField($field, $resolvedPrefix, $duplicates);
            }
            unset($field);
        }
        unset($tab);

        foreach ($schema['fields_catalog'] as &$field) {
            if (!is_array($field)) {
                continue;
            }

            $field = $this->enrichField($field, $resolvedPrefix, $duplicates);
        }
        unset($field);

        $writable = 0;
        foreach ((array) ($schema['fields_catalog'] ?? []) as $field) {
            if (is_array($field) && !empty($field['writable'])) {
                $writable++;
            }
        }

        $schema['resolved_prefix'] = $resolvedPrefix;
        $schema['stats']['writable_fields_total'] = $writable;
        $schema['stats']['duplicate_field_names_total'] = count($duplicates);

        return $schema;
    }

    /** @param array<string,mixed> $schema @return array<string,array<string,mixed>> */
    public function fieldsByName(array $schema): array
    {
        $result = [];
        foreach ((array) ($schema['fields_catalog'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $result[$name] = $field;
        }

        return $result;
    }

    /** @param array<string,mixed> $schema @return array<string,int> */
    private function duplicateNames(array $schema): array
    {
        $counts = [];
        foreach ((array) ($schema['fields_catalog'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $counts[$name] = (int) ($counts[$name] ?? 0) + 1;
        }

        return array_filter($counts, static fn (int $count): bool => $count > 1);
    }

    /** @param array<string,mixed> $schema */
    private function resolvePrefix(array $schema): string
    {
        $prefixCounts = [];

        foreach ((array) ($schema['fields_catalog'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            $settingName = trim((string) ($field['setting_name'] ?? ''));
            if ($name === '' || $settingName === '' || !str_ends_with($settingName, $name)) {
                continue;
            }

            $prefix = substr($settingName, 0, strlen($settingName) - strlen($name));
            $prefixCounts[$prefix] = (int) ($prefixCounts[$prefix] ?? 0) + 1;
        }

        if ($prefixCounts !== []) {
            arsort($prefixCounts);
            $prefix = (string) array_key_first($prefixCounts);
            if ($prefix !== '') {
                return $prefix;
            }
        }

        $configured = trim((string) $this->cfg('client_settings.write_prefix', ''));
        if ($configured !== '') {
            return $configured;
        }

        $prefixes = (array) $this->cfg('client_settings.setting_prefixes', ['client_', 'default_', '']);
        foreach ($prefixes as $prefix) {
            if (!is_string($prefix)) {
                continue;
            }

            return $prefix;
        }

        return 'client_';
    }

    /**
     * @param array<string,mixed> $field
     * @param array<string,int> $duplicates
     * @return array<string,mixed>
     */
    private function enrichField(array $field, string $resolvedPrefix, array $duplicates): array
    {
        $name = trim((string) ($field['name'] ?? ''));
        $type = strtolower(trim((string) ($field['type'] ?? '')));
        $settingName = trim((string) ($field['setting_name'] ?? ''));
        $matchedPrefix = '';

        if ($name !== '' && $settingName !== '' && str_ends_with($settingName, $name)) {
            $matchedPrefix = substr($settingName, 0, strlen($settingName) - strlen($name));
        }

        if ($settingName === '' && $name !== '') {
            $settingName = $resolvedPrefix . $name;
        }

        $hasDuplicateName = isset($duplicates[$name]);
        $writable = $name !== ''
            && !$hasDuplicateName
            && !in_array($type, ['title', 'divider'], true)
            && $settingName !== '';

        $field['setting_name'] = $settingName !== '' ? $settingName : null;
        $field['resolved_prefix'] = $matchedPrefix !== '' ? $matchedPrefix : $resolvedPrefix;
        $field['value_source'] = trim((string) ($field['value'] ?? '')) !== '' || trim((string) ($field['setting_name'] ?? '')) !== ''
            ? 'system_settings'
            : null;
        $field['writable'] = $writable;
        $field['duplicate_name'] = $hasDuplicateName;

        return $field;
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
