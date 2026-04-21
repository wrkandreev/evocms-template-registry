<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class BLangHealthService
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** @return array<string,mixed> */
    public function health(): array
    {
        $data = $this->collectDrift();

        return [
            'ok' => empty($data['errors']),
            'errors' => array_values($data['errors']),
            'warnings' => array_values($data['warnings']),
            'stats' => [
                'templates_with_errors' => count($data['errors']),
                'templates_with_warnings' => count($data['warnings']),
                'missing_blang_links_total' => $data['missing_blang_links_total'],
                'missing_modx_tv_links_total' => $data['missing_modx_tv_links_total'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function fixTemplateLinks(): array
    {
        $templateLinksTable = $this->requireTable('blang.template_links_table', 'blang_tmplvar_templates');
        $data = $this->collectDrift();
        $fixed = [];

        DB::transaction(function () use ($data, $templateLinksTable, &$fixed): void {
            foreach ($data['add_links'] as $templateId => $fieldIds) {
                foreach (array_unique(array_map('intval', $fieldIds)) as $fieldId) {
                    $exists = DB::table($templateLinksTable)
                        ->where('tmplvarid', $fieldId)
                        ->where('templateid', (int) $templateId)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    $payload = [
                        'tmplvarid' => $fieldId,
                        'templateid' => (int) $templateId,
                    ];
                    if ($this->hasColumn($templateLinksTable, 'rank')) {
                        $payload['rank'] = 0;
                    }
                    DB::table($templateLinksTable)->insert($payload);
                    $fixed[] = ['template_id' => (int) $templateId, 'field_id' => $fieldId];
                }
            }
        });

        if ($fixed !== []) {
            (new BLangFieldService($this->config))->syncAllFields();
        }

        return [
            'entity' => 'blang_template_links',
            'message' => 'bLang template links fixed.',
            'fixed' => $fixed,
            'fixed_total' => count($fixed),
            'health' => $this->health(),
        ];
    }

    /** @return array<string,mixed> */
    private function collectDrift(): array
    {
        $templatesTable = $this->requireTable('templates_table', 'site_templates');
        $tvsTable = $this->requireTable('tvs_table', 'site_tmplvars');
        $templateTvPivotTable = $this->requireTable('template_tv_pivot_table', 'site_tmplvar_templates');
        $fieldsTable = $this->requireTable('blang.fields_table', 'blang_tmplvars');
        $templateLinksTable = $this->requireTable('blang.template_links_table', 'blang_tmplvar_templates');

        $blang = (new BLangExtractor($this->config, $this->projectRoot()))->extract();
        $languages = array_values((array) ($blang['languages'] ?? []));
        $suffixes = is_array($blang['suffixes'] ?? null) ? $blang['suffixes'] : [];

        $templates = DB::table($templatesTable)->select(['id', 'templatename'])->orderBy('id')->get();
        $allFields = DB::table($fieldsTable)->select(['id', 'name'])->orderBy('id')->get();

        $errors = [];
        $warnings = [];
        $addLinks = [];
        $missingBttTotal = 0;
        $missingTvPivotTotal = 0;

        foreach ($templates as $template) {
            $templateId = (int) ($template->id ?? 0);
            if ($templateId <= 0) {
                continue;
            }

            $currentTemplateFields = DB::table($tvsTable . ' as tv')
                ->join($templateTvPivotTable . ' as tt', 'tv.id', '=', 'tt.tmplvarid')
                ->where('tt.templateid', $templateId)
                ->pluck('tv.name')
                ->map(static fn (mixed $value): string => (string) $value)
                ->all();

            $blangTemplateFieldNames = DB::table($fieldsTable . ' as bt')
                ->join($templateLinksTable . ' as btt', 'bt.id', '=', 'btt.tmplvarid')
                ->where('btt.templateid', $templateId)
                ->pluck('bt.name')
                ->map(static fn (mixed $value): string => (string) $value)
                ->all();

            foreach ($allFields as $field) {
                $fieldId = (int) ($field->id ?? 0);
                $baseName = (string) ($field->name ?? '');
                if ($fieldId <= 0 || $baseName === '') {
                    continue;
                }

                $localizedNames = [];
                foreach ($languages as $language) {
                    $fullName = $baseName . (string) ($suffixes[$language] ?? '');
                    if ($this->isDefaultField($fullName)) {
                        continue;
                    }
                    $localizedNames[] = $fullName;
                }

                $isFieldLinked = in_array($baseName, $blangTemplateFieldNames, true);
                foreach ($localizedNames as $tvName) {
                    if (in_array($tvName, $currentTemplateFields, true) && !$isFieldLinked) {
                        $errors[$templateId]['template_id'] = $templateId;
                        $errors[$templateId]['template_name'] = (string) ($template->templatename ?? '');
                        $errors[$templateId]['tv_names'][] = $tvName;
                        $addLinks[$templateId][] = $fieldId;
                        $missingBttTotal++;
                    }

                    if (!in_array($tvName, $currentTemplateFields, true) && $isFieldLinked) {
                        $warnings[$templateId]['template_id'] = $templateId;
                        $warnings[$templateId]['template_name'] = (string) ($template->templatename ?? '');
                        $warnings[$templateId]['tv_names'][] = $tvName;
                        $missingTvPivotTotal++;
                    }
                }
            }
        }

        foreach ($errors as &$item) {
            $item['tv_names'] = array_values(array_unique($item['tv_names'] ?? []));
        }
        foreach ($warnings as &$item) {
            $item['tv_names'] = array_values(array_unique($item['tv_names'] ?? []));
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'add_links' => $addLinks,
            'missing_blang_links_total' => $missingBttTotal,
            'missing_modx_tv_links_total' => $missingTvPivotTotal,
        ];
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

    private function hasColumn(string $table, string $column): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
    }

    private function resolveTableName(string $base): ?string
    {
        if ($base === '') {
            return null;
        }
        if (\Illuminate\Support\Facades\Schema::hasTable($base)) {
            return $base;
        }
        $defaultConnection = (string) \config('database.default');
        $prefix = (string) \config("database.connections.{$defaultConnection}.prefix", '');
        if ($prefix !== '') {
            $withPrefix = $prefix . $base;
            if (\Illuminate\Support\Facades\Schema::hasTable($withPrefix)) {
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
