<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BLangFieldService
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createField(array $input): array
    {
        $fieldsTable = $this->requireTable('blang.fields_table', 'blang_tmplvars');
        $name = $this->requireName($input);
        if ($this->valueExists($fieldsTable, 'name', $name)) {
            throw new RuntimeException('bLang field name already exists.');
        }

        $fieldId = 0;
        DB::transaction(function () use ($input, $fieldsTable, &$fieldId): void {
            $payload = $this->normalizeFieldPayload($input, $fieldsTable, []);
            $fieldId = (int) DB::table($fieldsTable)->insertGetId($payload);
            $this->replaceTemplateLinks($fieldId, $input['template_ids'] ?? null);
            $this->syncFieldById($fieldId);
        });

        return [
            'entity' => 'blang_field',
            'id' => $fieldId,
            'message' => 'bLang field created.',
            'field' => $this->findField($fieldId),
        ];
    }

    public function syncAllFields(): void
    {
        $fieldsTable = $this->requireTable('blang.fields_table', 'blang_tmplvars');
        $fieldIds = DB::table($fieldsTable)->orderBy('id')->pluck('id')->all();
        foreach ($fieldIds as $fieldId) {
            $this->syncFieldById((int) $fieldId);
        }
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateField(int $fieldId, array $input): array
    {
        $fieldsTable = $this->requireTable('blang.fields_table', 'blang_tmplvars');
        $field = DB::table($fieldsTable)->where('id', $fieldId)->first();
        if ($field === null) {
            throw new RuntimeException('bLang field not found.');
        }

        $payload = $this->normalizeFieldPayload($input, $fieldsTable, (array) $field, true);
        $oldName = (string) ($field->name ?? '');
        $newName = array_key_exists('name', $payload) ? (string) $payload['name'] : $oldName;

        if ($newName !== $oldName) {
            if ($this->valueExistsExceptId($fieldsTable, 'name', $newName, $fieldId)) {
                throw new RuntimeException('bLang field name already exists.');
            }
            $this->assertGeneratedTvNamesAvailable($oldName, $newName);
        }

        if ($payload === [] && !array_key_exists('template_ids', $input)) {
            throw new RuntimeException('No bLang field fields provided for update.');
        }

        DB::transaction(function () use ($fieldId, $fieldsTable, $payload, $input, $oldName, $newName): void {
            if ($payload !== []) {
                DB::table($fieldsTable)->where('id', $fieldId)->update($payload);
            }

            if ($newName !== $oldName) {
                $this->renameGeneratedTvs($oldName, $newName);
            }

            if (array_key_exists('template_ids', $input)) {
                $this->replaceTemplateLinks($fieldId, $input['template_ids']);
            }

            $this->syncFieldById($fieldId);
        });

        return [
            'entity' => 'blang_field',
            'id' => $fieldId,
            'message' => 'bLang field updated.',
            'field' => $this->findField($fieldId),
        ];
    }

    /** @return array<string,mixed> */
    public function deleteField(int $fieldId): array
    {
        $fieldsTable = $this->requireTable('blang.fields_table', 'blang_tmplvars');
        $templateLinksTable = $this->requireTable('blang.template_links_table', 'blang_tmplvar_templates');
        $field = DB::table($fieldsTable)->where('id', $fieldId)->first();
        if ($field === null) {
            throw new RuntimeException('bLang field not found.');
        }

        $entry = $this->findField($fieldId);
        DB::transaction(function () use ($fieldId, $fieldsTable, $templateLinksTable, $field): void {
            DB::table($templateLinksTable)->where('tmplvarid', $fieldId)->delete();
            DB::table($fieldsTable)->where('id', $fieldId)->delete();
            $this->deleteGeneratedTvs((string) ($field->name ?? ''));
        });

        return [
            'entity' => 'blang_field',
            'id' => $fieldId,
            'message' => 'bLang field deleted.',
            'field' => $entry,
        ];
    }

    /** @return array<string,mixed>|null */
    private function findField(int $fieldId): ?array
    {
        $fieldsTable = $this->requireTable('blang.fields_table', 'blang_tmplvars');
        $templateLinksTable = $this->requireTable('blang.template_links_table', 'blang_tmplvar_templates');
        $row = DB::table($fieldsTable)->where('id', $fieldId)->first();
        if ($row === null) {
            return null;
        }

        $templateIds = DB::table($templateLinksTable)
            ->where('tmplvarid', $fieldId)
            ->orderBy('templateid')
            ->pluck('templateid')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => (string) ($row->name ?? ''),
            'caption' => (string) ($row->caption ?? ''),
            'description' => (string) ($row->description ?? ''),
            'type' => (string) ($row->type ?? ''),
            'category' => isset($row->category) ? (int) $row->category : 0,
            'rank' => isset($row->rank) ? (int) $row->rank : 0,
            'tab' => (string) ($row->tab ?? ''),
            'default_text' => (string) ($row->default_text ?? ''),
            'template_ids' => $templateIds,
        ];
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $current @return array<string,mixed> */
    private function normalizeFieldPayload(array $input, string $fieldsTable, array $current = [], bool $partial = false): array
    {
        $columns = array_flip(Schema::getColumnListing($fieldsTable));
        $payload = [];

        $stringFields = ['type', 'name', 'caption', 'description', 'elements', 'display', 'display_params', 'default_text', 'multitv_translate_fields', 'tab'];
        foreach ($stringFields as $field) {
            if (!array_key_exists($field, $input)) {
                if ($partial) {
                    continue;
                }
                if ($field === 'name') {
                    $payload[$field] = $this->requireName($input);
                }
                continue;
            }

            $value = (string) ($input[$field] ?? '');
            if ($field === 'name') {
                $value = trim($value);
                if ($value === '') {
                    throw new RuntimeException('Required field "name" is empty.');
                }
            }
            if ($field === 'caption' && $value === '' && array_key_exists('name', $payload)) {
                $value = (string) $payload['name'];
            }
            if (isset($columns[$field])) {
                $payload[$field] = $value;
            }
        }

        if (!$partial && !isset($payload['caption']) && isset($columns['caption'])) {
            $payload['caption'] = (string) ($input['caption'] ?? $payload['name'] ?? '');
        }
        if (!$partial && !isset($payload['type']) && isset($columns['type'])) {
            $payload['type'] = (string) ($input['type'] ?? 'text');
        }

        foreach (['editor_type', 'locked', 'rank'] as $field) {
            if (!array_key_exists($field, $input)) {
                if ($partial) {
                    continue;
                }
            }
            if (isset($columns[$field]) && (array_key_exists($field, $input) || !$partial)) {
                $payload[$field] = (int) ($input[$field] ?? $current[$field] ?? 0);
            }
        }

        if (array_key_exists('category_id', $input) || array_key_exists('category', $input) || (!$partial && isset($columns['category']))) {
            $payload['category'] = $this->resolveCategory($input['category_id'] ?? $input['category'] ?? $current['category'] ?? 0);
            if (!isset($columns['category'])) {
                unset($payload['category']);
            }
        }

        return $payload;
    }

    private function resolveCategory(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        $category = trim((string) $value);
        if ($category === '') {
            return 0;
        }

        $categoriesTable = $this->requireTable('categories_table', 'categories');
        $existingId = (int) DB::table($categoriesTable)->where('category', $category)->value('id');
        if ($existingId > 0) {
            return $existingId;
        }

        return (int) DB::table($categoriesTable)->insertGetId(['category' => $category]);
    }

    private function replaceTemplateLinks(int $fieldId, mixed $templateIdsInput): void
    {
        $templateLinksTable = $this->requireTable('blang.template_links_table', 'blang_tmplvar_templates');
        $templatesTable = $this->requireTable('templates_table', 'site_templates');
        $hasRank = Schema::hasColumn($templateLinksTable, 'rank');
        $templateIds = [];

        if (is_array($templateIdsInput)) {
            foreach ($templateIdsInput as $templateId) {
                $templateId = (int) $templateId;
                if ($templateId <= 0) {
                    continue;
                }
                if (!DB::table($templatesTable)->where('id', $templateId)->exists()) {
                    throw new RuntimeException('Template not found.');
                }
                $templateIds[$templateId] = $templateId;
            }
        }

        DB::table($templateLinksTable)->where('tmplvarid', $fieldId)->delete();
        foreach ($templateIds as $templateId) {
            $payload = [
                'tmplvarid' => $fieldId,
                'templateid' => $templateId,
            ];
            if ($hasRank) {
                $payload['rank'] = 0;
            }
            DB::table($templateLinksTable)->insert($payload);
        }
    }

    private function syncFieldById(int $fieldId): void
    {
        $fieldsTable = $this->requireTable('blang.fields_table', 'blang_tmplvars');
        $templateLinksTable = $this->requireTable('blang.template_links_table', 'blang_tmplvar_templates');
        $tvsTable = $this->requireTable('tvs_table', 'site_tmplvars');
        $templateTvPivotTable = $this->requireTable('template_tv_pivot_table', 'site_tmplvar_templates');
        $field = DB::table($fieldsTable)->where('id', $fieldId)->first();
        if ($field === null) {
            return;
        }

        $languages = $this->languages();
        $suffixes = $this->suffixes($languages);
        $tvColumns = array_flip(Schema::getColumnListing($tvsTable));
        $templateLinks = DB::table($templateLinksTable)->where('tmplvarid', $fieldId)->get();

        foreach ($languages as $language) {
            $suffix = $suffixes[$language] ?? '';
            $tvName = (string) ($field->name ?? '') . $suffix;
            if ($this->isDefaultField($tvName)) {
                continue;
            }

            $payload = [
                'type' => (string) ($field->type ?? 'text'),
                'name' => $tvName,
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
            $payload = array_intersect_key($payload, $tvColumns);

            $tvId = (int) DB::table($tvsTable)->where('name', $tvName)->value('id');
            if ($tvId > 0) {
                DB::table($tvsTable)->where('id', $tvId)->update($payload);
            } else {
                $tvId = (int) DB::table($tvsTable)->insertGetId($payload);
            }

            DB::table($templateTvPivotTable)->where('tmplvarid', $tvId)->delete();
            foreach ($templateLinks as $link) {
                $pivotPayload = [
                    'tmplvarid' => $tvId,
                    'templateid' => (int) ($link->templateid ?? 0),
                ];
                if (Schema::hasColumn($templateTvPivotTable, 'rank')) {
                    $pivotPayload['rank'] = (int) ($link->rank ?? 0);
                }
                DB::table($templateTvPivotTable)->insert($pivotPayload);
            }
        }
    }

    private function renameGeneratedTvs(string $oldName, string $newName): void
    {
        if ($oldName === '' || $oldName === $newName) {
            return;
        }

        $tvsTable = $this->requireTable('tvs_table', 'site_tmplvars');
        $languages = $this->languages();
        $suffixes = $this->suffixes($languages);

        foreach ($languages as $language) {
            $suffix = $suffixes[$language] ?? '';
            $oldFullName = $oldName . $suffix;
            $newFullName = $newName . $suffix;
            if ($this->isDefaultField($oldFullName) || $oldFullName === $newFullName) {
                continue;
            }

            $oldId = (int) DB::table($tvsTable)->where('name', $oldFullName)->value('id');
            $newId = (int) DB::table($tvsTable)->where('name', $newFullName)->value('id');
            if ($oldId > 0 && $newId > 0 && $oldId !== $newId) {
                throw new RuntimeException('bLang generated TV name already exists.');
            }

            if ($oldId > 0) {
                DB::table($tvsTable)->where('id', $oldId)->update(['name' => $newFullName]);
            }
        }
    }

    private function deleteGeneratedTvs(string $baseName): void
    {
        if ($baseName === '') {
            return;
        }

        $tvsTable = $this->requireTable('tvs_table', 'site_tmplvars');
        $templateTvPivotTable = $this->requireTable('template_tv_pivot_table', 'site_tmplvar_templates');
        $tvValuesTable = $this->requireTable('tv_values_table', 'site_tmplvar_contentvalues');
        $languages = $this->languages();
        $suffixes = $this->suffixes($languages);

        foreach ($languages as $language) {
            $tvName = $baseName . ($suffixes[$language] ?? '');
            if ($this->isDefaultField($tvName)) {
                continue;
            }

            $tvId = (int) DB::table($tvsTable)->where('name', $tvName)->value('id');
            if ($tvId <= 0) {
                continue;
            }

            DB::table($templateTvPivotTable)->where('tmplvarid', $tvId)->delete();
            DB::table($tvValuesTable)->where('tmplvarid', $tvId)->delete();
            DB::table($tvsTable)->where('id', $tvId)->delete();
        }
    }

    private function assertGeneratedTvNamesAvailable(string $oldName, string $newName): void
    {
        if ($oldName === $newName || $oldName === '' || $newName === '') {
            return;
        }

        $tvsTable = $this->requireTable('tvs_table', 'site_tmplvars');
        $languages = $this->languages();
        $suffixes = $this->suffixes($languages);

        foreach ($languages as $language) {
            $suffix = $suffixes[$language] ?? '';
            $oldFullName = $oldName . $suffix;
            $newFullName = $newName . $suffix;
            if ($this->isDefaultField($oldFullName)) {
                continue;
            }

            $oldId = (int) DB::table($tvsTable)->where('name', $oldFullName)->value('id');
            $newId = (int) DB::table($tvsTable)->where('name', $newFullName)->value('id');
            if ($oldId > 0 && $newId > 0 && $oldId !== $newId) {
                throw new RuntimeException('bLang generated TV name already exists.');
            }
        }
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

    /** @param array<string,mixed> $input */
    private function requireName(array $input): string
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Required field "name" is empty.');
        }

        return $name;
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

    private function valueExists(string $table, string $column, string $value): bool
    {
        if (!Schema::hasColumn($table, $column)) {
            return false;
        }

        return DB::table($table)->where($column, $value)->exists();
    }

    private function valueExistsExceptId(string $table, string $column, string $value, int $id): bool
    {
        if (!Schema::hasColumn($table, $column)) {
            return false;
        }

        return DB::table($table)
            ->where($column, $value)
            ->where('id', '!=', $id)
            ->exists();
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
