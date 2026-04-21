<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BLangSettingsService
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateSettings(array $input): array
    {
        $settingsTable = $this->requireTable('blang.settings_table', 'blang_settings');
        $lexiconTable = $this->requireTable('blang.lexicon_table', 'blang');
        $extractor = new BLangExtractor($this->config, $this->projectRoot());
        $current = $extractor->extract();
        $currentLanguages = array_values((array) ($current['languages'] ?? []));
        $currentSuffixes = is_array($current['suffixes'] ?? null) ? $current['suffixes'] : [];

        $updates = $this->normalizeSettingsInput($input, $current);
        if ($updates === []) {
            throw new RuntimeException('No bLang settings provided for update.');
        }

        $languagesToCreate = [];
        if (array_key_exists('languages', $updates)) {
            $newLanguages = $this->parseLanguages((string) $updates['languages']);
            if ($newLanguages === []) {
                throw new RuntimeException('bLang languages setting is invalid.');
            }
            $languagesToCreate = $newLanguages;
        }

        if (array_key_exists('suffixes', $updates)) {
            $languages = $languagesToCreate !== [] ? $languagesToCreate : $currentLanguages;
            $suffixes = $this->parseSuffixes((string) $updates['suffixes']);
            $this->validateSuffixes($languages, $suffixes);
        }

        if (array_key_exists('default', $updates)) {
            $defaultLanguage = trim((string) $updates['default']);
            $languages = $languagesToCreate !== [] ? $languagesToCreate : $currentLanguages;
            if ($defaultLanguage === '' || !in_array($defaultLanguage, $languages, true)) {
                throw new RuntimeException('bLang default language is invalid.');
            }
        }

        DB::transaction(function () use ($settingsTable, $lexiconTable, $updates, $languagesToCreate): void {
            foreach ($updates as $name => $value) {
                $existing = DB::table($settingsTable)->where('name', $name)->exists();
                $payload = ['name' => $name, 'value' => (string) $value];
                if ($existing) {
                    DB::table($settingsTable)->where('name', $name)->update(['value' => (string) $value]);
                } else {
                    DB::table($settingsTable)->insert($payload);
                }
            }

            $this->ensureLexiconColumns($lexiconTable, $languagesToCreate);
        });

        // Re-sync generated TVs so changed suffixes/languages are reflected in site_tmplvars.
        (new BLangFieldService($this->config))->syncAllFields();

        return [
            'entity' => 'blang_settings',
            'message' => 'bLang settings updated.',
            'blang' => (new BLangExtractor($this->config, $this->projectRoot()))->extract(),
        ];
    }

    /** @return array<string,mixed> */
    public function removeLanguage(string $language, ?string $newDefault = null): array
    {
        $language = trim($language);
        if ($language === '') {
            throw new RuntimeException('bLang language is invalid.');
        }

        $settingsTable = $this->requireTable('blang.settings_table', 'blang_settings');
        $extractor = new BLangExtractor($this->config, $this->projectRoot());
        $current = $extractor->extract();
        $languages = array_values((array) ($current['languages'] ?? []));
        if (!in_array($language, $languages, true)) {
            throw new RuntimeException('bLang language not found.');
        }
        if (count($languages) <= 1) {
            throw new RuntimeException('Cannot remove the last bLang language.');
        }

        $languages = array_values(array_filter($languages, static fn (string $item): bool => $item !== $language));
        $suffixes = is_array($current['suffixes'] ?? null) ? $current['suffixes'] : [];
        unset($suffixes[$language]);

        $currentDefault = (string) ($current['default_language'] ?? '');
        if ($currentDefault === $language) {
            $newDefault = trim((string) ($newDefault ?? ''));
            if ($newDefault === '' || !in_array($newDefault, $languages, true)) {
                throw new RuntimeException('bLang replacement default language is invalid.');
            }
        } else {
            $newDefault = $currentDefault;
        }

        DB::transaction(function () use ($settingsTable, $languages, $suffixes, $newDefault): void {
            $this->upsertSetting($settingsTable, 'languages', implode('||', $languages));
            $pairs = [];
            foreach ($languages as $item) {
                $pairs[] = $item . '==' . (string) ($suffixes[$item] ?? '');
            }
            $this->upsertSetting($settingsTable, 'suffixes', implode('||', $pairs));
            $this->upsertSetting($settingsTable, 'default', $newDefault);
        });

        (new BLangFieldService($this->config))->syncAllFields();

        return [
            'entity' => 'blang_language',
            'message' => 'bLang language removed from settings.',
            'removed_language' => $language,
            'blang' => (new BLangExtractor($this->config, $this->projectRoot()))->extract(),
        ];
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $current @return array<string,string> */
    private function normalizeSettingsInput(array $input, array $current): array
    {
        $allowedKeys = [
            'languages',
            'suffixes',
            'default',
            'autoFields',
            'autoUrl',
            'default_to_new_tab',
            'fields',
            'translate',
            'translate_provider',
            'clientSettingsPrefix',
            'pb_show_btn',
            'pb_is_te3',
            'pb_config',
            'menu_controller_fields',
            'content_controller_fields',
        ];

        $result = [];
        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            if (in_array($key, ['languages', 'fields'], true) && is_array($value)) {
                $value = implode('||', array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value), static fn (string $item): bool => $item !== '')));
            }
            if (in_array($key, ['menu_controller_fields', 'content_controller_fields'], true) && is_array($value)) {
                $value = implode(',', array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value), static fn (string $item): bool => $item !== '')));
            }
            if ($key === 'suffixes' && is_array($value)) {
                $pairs = [];
                foreach ($value as $language => $suffix) {
                    $language = trim((string) $language);
                    if ($language === '') {
                        continue;
                    }
                    $pairs[] = $language . '==' . (string) $suffix;
                }
                $value = implode('||', $pairs);
            }
            if (in_array($key, ['autoFields', 'autoUrl', 'default_to_new_tab', 'translate', 'pb_show_btn', 'pb_is_te3'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
            }

            $stringValue = trim((string) $value);
            if ($key === 'default' && $stringValue === '') {
                $stringValue = (string) ($current['default_language'] ?? '');
            }

            $result[$key] = $stringValue;
        }

        return $result;
    }

    /** @return array<int,string> */
    private function parseLanguages(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode('||', $value)), static fn (string $item): bool => $item !== ''));
    }

    /** @return array<string,string> */
    private function parseSuffixes(string $value): array
    {
        $result = [];
        foreach ($this->parseLanguages($value) as $item) {
            $parts = explode('==', $item, 2);
            $language = trim((string) ($parts[0] ?? ''));
            if ($language === '') {
                continue;
            }
            $result[$language] = (string) ($parts[1] ?? '');
        }

        return $result;
    }

    /** @param array<int,string> $languages @param array<string,string> $suffixes */
    private function validateSuffixes(array $languages, array $suffixes): void
    {
        $used = [];
        foreach ($languages as $language) {
            if (!array_key_exists($language, $suffixes)) {
                throw new RuntimeException('bLang suffixes setting is invalid.');
            }
            $suffix = (string) $suffixes[$language];
            if ($suffix !== '' && in_array($suffix, $used, true)) {
                throw new RuntimeException('bLang suffixes setting is invalid.');
            }
            $used[] = $suffix;
        }
    }

    private function upsertSetting(string $settingsTable, string $name, string $value): void
    {
        $existing = DB::table($settingsTable)->where('name', $name)->exists();
        if ($existing) {
            DB::table($settingsTable)->where('name', $name)->update(['value' => $value]);
            return;
        }
        DB::table($settingsTable)->insert(['name' => $name, 'value' => $value]);
    }

    /** @param array<int,string> $languages */
    private function ensureLexiconColumns(string $lexiconTable, array $languages): void
    {
        foreach ($languages as $language) {
            if (Schema::hasColumn($lexiconTable, $language)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE %s ADD `%s` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT \"\"',
                $lexiconTable,
                str_replace('`', '', $language)
            ));
        }
    }

    private function projectRoot(): string
    {
        $basePath = function_exists('base_path') ? (string) base_path() : (getcwd() ?: '');
        return dirname($basePath);
    }

    private function requireTable(string $configKey, string $default): string
    {
        $table = $this->resolveTableName((string) $this->cfg($configKey, $default));
        if ($table === null) {
            throw new RuntimeException('Required table not found for ' . $configKey . '.');
        }

        return $table;
    }

    private function resolveTableName(string $base): ?string
    {
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
