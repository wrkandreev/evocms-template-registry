<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BLangDefaultParamsService
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** @return array<string,mixed> */
    public function createDefaultParams(bool $attachAllTemplates = false): array
    {
        $fieldsTable = $this->requireTable('blang.fields_table', 'blang_tmplvars');
        $templateLinksTable = $this->requireTable('blang.template_links_table', 'blang_tmplvar_templates');
        $templatesTable = $this->requireTable('templates_table', 'site_templates');

        $defaults = $this->loadDefaultFields();
        if ($defaults === []) {
            throw new RuntimeException('bLang default fields definition is empty.');
        }

        $created = [];
        $existing = [];

        DB::transaction(function () use ($defaults, $fieldsTable, $templateLinksTable, $templatesTable, $attachAllTemplates, &$created, &$existing): void {
            foreach ($defaults as $field) {
                $name = trim((string) ($field['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $existingId = (int) DB::table($fieldsTable)->where('name', $name)->value('id');
                if ($existingId > 0) {
                    $fieldId = $existingId;
                    $existing[] = $name;
                } else {
                    $payload = $this->normalizeDefaultFieldPayload($field, $fieldsTable);
                    $fieldId = (int) DB::table($fieldsTable)->insertGetId($payload);
                    $created[] = $name;
                }

                if (!$attachAllTemplates || $fieldId <= 0) {
                    continue;
                }

                $templateIds = DB::table($templatesTable)->orderBy('id')->pluck('id')->all();
                foreach ($templateIds as $templateId) {
                    DB::table($templateLinksTable)->updateOrInsert([
                        'tmplvarid' => (int) $fieldId,
                        'templateid' => (int) $templateId,
                    ], [
                        'rank' => 0,
                    ]);
                }
            }

            $this->syncGeneratedTvs($fieldsTable, $templateLinksTable);
        });

        return [
            'entity' => 'blang_default_params',
            'message' => 'bLang default parameters processed.',
            'created' => $created,
            'existing' => $existing,
            'attach_all_templates' => $attachAllTemplates,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function loadDefaultFields(): array
    {
        $path = dirname(__DIR__, 2) . '/resources/blang/default_fields.json';
        if (!is_file($path)) {
            throw new RuntimeException('bLang default fields definition not found.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $field @return array<string,mixed> */
    private function normalizeDefaultFieldPayload(array $field, string $fieldsTable): array
    {
        $categoryName = trim((string) ($field['category'] ?? ''));
        $payload = [
            'type' => (string) ($field['type'] ?? 'text'),
            'name' => trim((string) ($field['name'] ?? '')),
            'caption' => (string) ($field['caption'] ?? ''),
            'description' => (string) ($field['description'] ?? ''),
            'editor_type' => (int) ($field['editor_type'] ?? 0),
            'category' => $categoryName === '' ? 0 : $this->resolveCategoryId($categoryName),
            'locked' => (int) ($field['locked'] ?? 0),
            'elements' => (string) ($field['elements'] ?? ''),
            'rank' => (int) ($field['rank'] ?? 0),
            'display' => (string) ($field['display'] ?? ''),
            'display_params' => (string) ($field['display_params'] ?? ''),
            'default_text' => (string) ($field['default_text'] ?? ''),
            'tab' => (string) ($field['tab'] ?? ''),
        ];

        $columns = array_flip(Schema::getColumnListing($fieldsTable));
        return array_intersect_key($payload, $columns);
    }

    private function resolveCategoryId(string $categoryName): int
    {
        $categoriesTable = $this->requireTable('categories_table', 'categories');
        $existingId = (int) DB::table($categoriesTable)->where('category', $categoryName)->value('id');
        if ($existingId > 0) {
            return $existingId;
        }

        return (int) DB::table($categoriesTable)->insertGetId(['category' => $categoryName]);
    }

    private function syncGeneratedTvs(string $fieldsTable, string $templateLinksTable): void
    {
        $tvsTable = $this->requireTable('tvs_table', 'site_tmplvars');
        $templateTvPivotTable = $this->requireTable('template_tv_pivot_table', 'site_tmplvar_templates');
        $languages = $this->languages();
        $suffixes = $this->suffixes($languages);
        $fields = DB::table($fieldsTable)->orderBy('id')->get();

        foreach ($fields as $field) {
            $baseName = trim((string) ($field->name ?? ''));
            if ($baseName === '') {
                continue;
            }

            foreach ($languages as $language) {
                $tvName = $baseName . ($suffixes[$language] ?? '');
                if ($this->isDefaultField($tvName)) {
                    continue;
                }

                $tvPayload = $this->prepareLocalizedTvPayload($field, $language, $suffixes[$language] ?? '', $tvsTable);
                $tvId = (int) DB::table($tvsTable)->where('name', $tvName)->value('id');
                if ($tvId > 0) {
                    DB::table($tvsTable)->where('id', $tvId)->update($tvPayload);
                } else {
                    $tvId = (int) DB::table($tvsTable)->insertGetId($tvPayload);
                }

                DB::table($templateTvPivotTable)->where('tmplvarid', $tvId)->delete();
                $links = DB::table($templateLinksTable)->where('tmplvarid', (int) ($field->id ?? 0))->get();
                foreach ($links as $link) {
                    DB::table($templateTvPivotTable)->insert([
                        'tmplvarid' => $tvId,
                        'templateid' => (int) ($link->templateid ?? 0),
                    ] + (Schema::hasColumn($templateTvPivotTable, 'rank') ? ['rank' => (int) ($link->rank ?? 0)] : []));
                }
            }
        }
    }

    /** @return array<string,mixed> */
    private function prepareLocalizedTvPayload(object $field, string $language, string $suffix, string $tvsTable): array
    {
        $columns = array_flip(Schema::getColumnListing($tvsTable));
        $payload = [
            'type' => (string) ($field->type ?? 'text'),
            'name' => (string) ($field->name ?? '') . $suffix,
            'caption' => $this->replacePlaceholders((string) ($field->caption ?? ''), $language, $suffix),
            'description' => $this->replacePlaceholders((string) ($field->description ?? ''), $language, $suffix),
            'editor_type' => (int) ($field->editor_type ?? 0),
            'category' => (int) ($field->category ?? 0),
            'locked' => (int) ($field->locked ?? 0),
            'elements' => $this->replacePlaceholders((string) ($field->elements ?? ''), $language, $suffix),
            'rank' => (int) ($field->rank ?? 0),
            'display' => (string) ($field->display ?? ''),
            'display_params' => $this->replacePlaceholders((string) ($field->display_params ?? ''), $language, $suffix),
            'default_text' => $this->replacePlaceholders((string) ($field->default_text ?? ''), $language, $suffix),
        ];

        return array_intersect_key($payload, $columns);
    }

    private function replacePlaceholders(string $value, string $language, string $suffix): string
    {
        return str_replace(['[lang]', '[suffix]'], [$language, $suffix], $value);
    }

    /** @return array<int,string> */
    private function languages(): array
    {
        $extractor = new BLangExtractor($this->config, $this->projectRoot());
        return array_values(array_filter((array) ($extractor->extract()['languages'] ?? []), static fn (mixed $lang): bool => is_string($lang) && trim($lang) !== ''));
    }

    /** @param array<int,string> $languages @return array<string,string> */
    private function suffixes(array $languages): array
    {
        $extractor = new BLangExtractor($this->config, $this->projectRoot());
        $payload = $extractor->extract();
        $source = is_array($payload['suffixes'] ?? null) ? $payload['suffixes'] : [];
        $result = [];
        foreach ($languages as $language) {
            $result[$language] = (string) ($source[$language] ?? '');
        }

        return $result;
    }

    private function projectRoot(): string
    {
        $basePath = function_exists('base_path') ? (string) base_path() : (getcwd() ?: '');
        return dirname($basePath);
    }

    private function isDefaultField(string $field): bool
    {
        return in_array($field, [
            'id', 'type', 'contentType', 'pagetitle', 'longtitle', 'description', 'alias', 'link_attributes', 'published', 'pub_date',
            'unpub_date', 'parent', 'isfolder', 'introtext', 'content', 'richtext', 'template', 'menuindex', 'searchable',
            'cacheable', 'createdon', 'createdby', 'editedon', 'editedby', 'deleted', 'deletedon', 'deletedby', 'publishedon',
            'publishedby', 'menutitle', 'donthit', 'privateweb', 'privatemgr', 'content_dispo', 'hidemenu', 'alias_visible',
        ], true);
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
