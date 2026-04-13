<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class TemplateRegistryWriteService
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createTemplate(array $input): array
    {
        $table = $this->requireTable('templates_table', 'site_templates');
        $columns = $this->columnMap($table);

        $name = trim((string) ($input['name'] ?? $input['templatename'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Required field "name" is empty.');
        }

        $alias = trim((string) ($input['alias'] ?? $input['templatealias'] ?? Str::slug($name)));
        if ($alias === '') {
            $alias = 'template-' . time();
        }

        if (isset($columns['templatename']) && $this->valueExists($table, 'templatename', $name)) {
            throw new RuntimeException('Template name already exists.');
        }
        if (isset($columns['templatealias']) && $this->valueExists($table, 'templatealias', $alias)) {
            throw new RuntimeException('Template alias already exists.');
        }

        $payload = [];
        $this->assignIfColumn($payload, $columns, 'templatename', $name);
        $this->assignIfColumn($payload, $columns, 'templatealias', $alias);
        $this->assignIfColumn($payload, $columns, 'description', (string) ($input['description'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'content', (string) ($input['content'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'icon', (string) ($input['icon'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'templatecontroller', (string) ($input['controller'] ?? $input['templatecontroller'] ?? ''));

        $view = trim((string) ($input['view'] ?? ''));
        foreach (['templateview', 'view', 'template_view'] as $column) {
            if ($view !== '' && isset($columns[$column])) {
                $payload[$column] = $view;
                break;
            }
        }

        $id = DB::table($table)->insertGetId($payload);
        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'template',
            'id' => (int) $id,
            'message' => 'Template created.',
            'regenerated' => $regenerated,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateTemplate(int $templateId, array $input): array
    {
        $table = $this->requireTable('templates_table', 'site_templates');
        $columns = $this->columnMap($table);
        $this->assertExists($table, $templateId, 'Template');

        $payload = [];

        if (array_key_exists('name', $input) || array_key_exists('templatename', $input)) {
            $name = trim((string) ($input['name'] ?? $input['templatename'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Required field "name" is empty.');
            }
            if (isset($columns['templatename']) && $this->valueExistsExceptId($table, 'templatename', $name, $templateId)) {
                throw new RuntimeException('Template name already exists.');
            }
            $this->assignIfColumn($payload, $columns, 'templatename', $name);
        }

        if (array_key_exists('alias', $input) || array_key_exists('templatealias', $input)) {
            $alias = trim((string) ($input['alias'] ?? $input['templatealias'] ?? ''));
            if ($alias === '') {
                throw new RuntimeException('Required field "alias" is empty.');
            }
            if (isset($columns['templatealias']) && $this->valueExistsExceptId($table, 'templatealias', $alias, $templateId)) {
                throw new RuntimeException('Template alias already exists.');
            }
            $this->assignIfColumn($payload, $columns, 'templatealias', $alias);
        }

        foreach (['description', 'content', 'icon'] as $field) {
            if (array_key_exists($field, $input)) {
                $this->assignIfColumn($payload, $columns, $field, (string) $input[$field]);
            }
        }

        if (array_key_exists('controller', $input) || array_key_exists('templatecontroller', $input)) {
            $this->assignIfColumn($payload, $columns, 'templatecontroller', (string) ($input['controller'] ?? $input['templatecontroller'] ?? ''));
        }

        if (array_key_exists('view', $input) || array_key_exists('templateview', $input) || array_key_exists('template_view', $input)) {
            $view = trim((string) ($input['view'] ?? $input['templateview'] ?? $input['template_view'] ?? ''));
            foreach (['templateview', 'view', 'template_view'] as $column) {
                if (isset($columns[$column])) {
                    $payload[$column] = $view;
                    break;
                }
            }
        }

        if ($payload === []) {
            throw new RuntimeException('No template fields provided for update.');
        }

        DB::table($table)->where('id', $templateId)->update($payload);
        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'template',
            'id' => $templateId,
            'message' => 'Template updated.',
            'regenerated' => $regenerated,
        ];
    }

    /** @return array<string,mixed> */
    public function deleteTemplate(int $templateId): array
    {
        $table = $this->requireTable('templates_table', 'site_templates');
        $contentTable = $this->requireTable('resources_table', 'site_content');
        $pivotTable = $this->requireTable('template_tv_pivot_table', 'site_tmplvar_templates');
        $this->assertExists($table, $templateId, 'Template');

        $resourceQuery = DB::table($contentTable)->where('template', $templateId);
        if (Schema::hasColumn($contentTable, 'deleted')) {
            $resourceQuery->where('deleted', 0);
        }
        $resourceCount = $resourceQuery->count();
        if ($resourceCount > 0) {
            throw new RuntimeException('Template is used by existing resources.');
        }

        DB::transaction(function () use ($table, $pivotTable, $templateId): void {
            DB::table($pivotTable)->where('templateid', $templateId)->delete();
            DB::table($table)->where('id', $templateId)->delete();
        });

        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'template',
            'id' => $templateId,
            'message' => 'Template deleted.',
            'regenerated' => $regenerated,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createTv(array $input): array
    {
        $table = $this->requireTable('tvs_table', 'site_tmplvars');
        $columns = $this->columnMap($table);

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Required field "name" is empty.');
        }

        if ($this->valueExists($table, 'name', $name)) {
            throw new RuntimeException('TV name already exists.');
        }

        $type = trim((string) ($input['type'] ?? 'text'));
        if ($type === '') {
            throw new RuntimeException('TV type is invalid.');
        }

        $payload = [];
        $this->assignIfColumn($payload, $columns, 'name', $name);
        $this->assignIfColumn($payload, $columns, 'type', $type);
        $this->assignIfColumn($payload, $columns, 'caption', (string) ($input['caption'] ?? $name));
        $this->assignIfColumn($payload, $columns, 'description', (string) ($input['description'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'default_text', (string) ($input['default_text'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'elements', (string) ($input['elements'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'display', (string) ($input['display'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'display_params', (string) ($input['display_params'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'editor_type', (int) ($input['editor_type'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'category', (int) ($input['category'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'lockcategory', (int) ($input['lockcategory'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'rank', (int) ($input['rank'] ?? 0));

        $id = DB::table($table)->insertGetId($payload);
        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'tv',
            'id' => (int) $id,
            'message' => 'TV created.',
            'regenerated' => $regenerated,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateTv(int $tvId, array $input): array
    {
        $table = $this->requireTable('tvs_table', 'site_tmplvars');
        $columns = $this->columnMap($table);
        $this->assertExists($table, $tvId, 'TV');

        $payload = [];

        if (array_key_exists('name', $input)) {
            $name = trim((string) ($input['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Required field "name" is empty.');
            }
            if ($this->valueExistsExceptId($table, 'name', $name, $tvId)) {
                throw new RuntimeException('TV name already exists.');
            }
            $this->assignIfColumn($payload, $columns, 'name', $name);
        }

        if (array_key_exists('type', $input)) {
            $type = trim((string) ($input['type'] ?? ''));
            if ($type === '') {
                throw new RuntimeException('TV type is invalid.');
            }
            $this->assignIfColumn($payload, $columns, 'type', $type);
        }

        foreach (['caption', 'description', 'default_text', 'elements', 'display', 'display_params'] as $field) {
            if (array_key_exists($field, $input)) {
                $this->assignIfColumn($payload, $columns, $field, (string) $input[$field]);
            }
        }

        foreach (['editor_type', 'category', 'lockcategory', 'rank'] as $field) {
            if (array_key_exists($field, $input)) {
                $this->assignIfColumn($payload, $columns, $field, (int) $input[$field]);
            }
        }

        if ($payload === []) {
            throw new RuntimeException('No TV fields provided for update.');
        }

        DB::table($table)->where('id', $tvId)->update($payload);
        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'tv',
            'id' => $tvId,
            'message' => 'TV updated.',
            'regenerated' => $regenerated,
        ];
    }

    /** @return array<string,mixed> */
    public function deleteTv(int $tvId): array
    {
        $table = $this->requireTable('tvs_table', 'site_tmplvars');
        $pivotTable = $this->requireTable('template_tv_pivot_table', 'site_tmplvar_templates');
        $valuesTable = $this->requireTable('tv_values_table', 'site_tmplvar_contentvalues');
        $this->assertExists($table, $tvId, 'TV');

        DB::transaction(function () use ($table, $pivotTable, $valuesTable, $tvId): void {
            DB::table($pivotTable)->where('tmplvarid', $tvId)->delete();
            DB::table($valuesTable)->where('tmplvarid', $tvId)->delete();
            DB::table($table)->where('id', $tvId)->delete();
        });

        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'tv',
            'id' => $tvId,
            'message' => 'TV deleted.',
            'regenerated' => $regenerated,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createResource(array $input): array
    {
        $contentTable = $this->requireTable('resources_table', 'site_content');
        $columns = $this->columnMap($contentTable);
        $tvValues = isset($input['tv_values']) && is_array($input['tv_values']) ? $input['tv_values'] : [];

        $title = trim((string) ($input['pagetitle'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Required field "pagetitle" is empty.');
        }

        $templateId = (int) ($input['template_id'] ?? $input['template'] ?? 0);
        if ($templateId > 0) {
            $this->assertExists($this->requireTable('templates_table', 'site_templates'), $templateId, 'Template');
        }

        $alias = trim((string) ($input['alias'] ?? Str::slug($title)));
        $payload = [];
        $this->assignIfColumn($payload, $columns, 'type', (string) ($input['type'] ?? 'document'));
        $this->assignIfColumn($payload, $columns, 'contentType', (string) ($input['content_type'] ?? 'text/html'));
        $this->assignIfColumn($payload, $columns, 'content', (string) ($input['content'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'pagetitle', $title);
        $this->assignIfColumn($payload, $columns, 'longtitle', (string) ($input['longtitle'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'description', (string) ($input['description'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'alias', $alias);
        $this->assignIfColumn($payload, $columns, 'link_attributes', (string) ($input['link_attributes'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'published', $this->boolInt($input['published'] ?? true));
        $this->assignIfColumn($payload, $columns, 'pub_date', (int) ($input['pub_date'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'unpub_date', (int) ($input['unpub_date'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'parent', (int) ($input['parent'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'isfolder', $this->boolInt($input['isfolder'] ?? false));
        $this->assignIfColumn($payload, $columns, 'introtext', (string) ($input['introtext'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'richtext', $this->boolInt($input['richtext'] ?? false));
        $this->assignIfColumn($payload, $columns, 'template', $templateId);
        $this->assignIfColumn($payload, $columns, 'menuindex', (int) ($input['menuindex'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'searchable', $this->boolInt($input['searchable'] ?? true));
        $this->assignIfColumn($payload, $columns, 'cacheable', $this->boolInt($input['cacheable'] ?? true));
        $this->assignIfColumn($payload, $columns, 'createdon', (int) ($input['createdon'] ?? time()));
        $this->assignIfColumn($payload, $columns, 'editedon', (int) ($input['editedon'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'deleted', $this->boolInt($input['deleted'] ?? false));
        $this->assignIfColumn($payload, $columns, 'deletedon', (int) ($input['deletedon'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'publishedon', (int) ($input['publishedon'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'menutitle', (string) ($input['menutitle'] ?? ''));
        $this->assignIfColumn($payload, $columns, 'hide_from_tree', $this->boolInt($input['hide_from_tree'] ?? false));
        $this->assignIfColumn($payload, $columns, 'privateweb', $this->boolInt($input['privateweb'] ?? false));
        $this->assignIfColumn($payload, $columns, 'privatemgr', $this->boolInt($input['privatemgr'] ?? false));
        $this->assignIfColumn($payload, $columns, 'content_dispo', (int) ($input['content_dispo'] ?? 0));
        $this->assignIfColumn($payload, $columns, 'hidemenu', $this->boolInt($input['hidemenu'] ?? false));
        $this->assignIfColumn($payload, $columns, 'alias_visible', $this->boolInt($input['alias_visible'] ?? true));

        $result = DB::transaction(function () use ($contentTable, $payload, $tvValues): array {
            $resourceId = (int) DB::table($contentTable)->insertGetId($payload);

            foreach ($tvValues as $tvId => $value) {
                $tvId = (int) $tvId;
                if ($tvId <= 0) {
                    continue;
                }
                $this->setResourceTvValueInternal($resourceId, $tvId, $value);
            }

            return ['resource_id' => $resourceId];
        });

        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'resource',
            'id' => (int) ($result['resource_id'] ?? 0),
            'message' => 'Resource created.',
            'regenerated' => $regenerated,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateResource(int $resourceId, array $input): array
    {
        $contentTable = $this->requireTable('resources_table', 'site_content');
        $columns = $this->columnMap($contentTable);
        $this->assertExists($contentTable, $resourceId, 'Resource');
        $tvValues = isset($input['tv_values']) && is_array($input['tv_values']) ? $input['tv_values'] : [];

        $payload = [];

        foreach (['type', 'content'] as $field) {
            if (array_key_exists($field, $input)) {
                $this->assignIfColumn($payload, $columns, $field, (string) $input[$field]);
            }
        }

        if (array_key_exists('content_type', $input)) {
            $this->assignIfColumn($payload, $columns, 'contentType', (string) $input['content_type']);
        }

        foreach (['pagetitle', 'longtitle', 'description', 'alias', 'link_attributes', 'introtext', 'menutitle'] as $field) {
            if (array_key_exists($field, $input)) {
                $value = trim((string) $input[$field]);
                if ($field === 'pagetitle' && $value === '') {
                    throw new RuntimeException('Required field "pagetitle" is empty.');
                }
                $this->assignIfColumn($payload, $columns, $field, $value);
            }
        }

        if (array_key_exists('template_id', $input) || array_key_exists('template', $input)) {
            $templateId = (int) ($input['template_id'] ?? $input['template'] ?? 0);
            if ($templateId <= 0) {
                throw new RuntimeException('Template id must be greater than zero.');
            }
            $this->assertExists($this->requireTable('templates_table', 'site_templates'), $templateId, 'Template');
            $this->assignIfColumn($payload, $columns, 'template', $templateId);
        }

        foreach (['pub_date', 'unpub_date', 'parent', 'menuindex', 'createdon', 'deletedon', 'publishedon', 'content_dispo'] as $field) {
            if (array_key_exists($field, $input)) {
                $this->assignIfColumn($payload, $columns, $field, (int) $input[$field]);
            }
        }

        foreach (['published', 'isfolder', 'richtext', 'searchable', 'cacheable', 'deleted', 'hide_from_tree', 'privateweb', 'privatemgr', 'hidemenu', 'alias_visible'] as $field) {
            if (array_key_exists($field, $input)) {
                $this->assignIfColumn($payload, $columns, $field, $this->boolInt($input[$field]));
            }
        }

        if (Schema::hasColumn($contentTable, 'editedon')) {
            $payload['editedon'] = time();
        }

        if ($payload === [] && $tvValues === []) {
            throw new RuntimeException('No resource fields provided for update.');
        }

        DB::transaction(function () use ($contentTable, $resourceId, $payload, $tvValues): void {
            if ($payload !== []) {
                DB::table($contentTable)->where('id', $resourceId)->update($payload);
            }

            foreach ($tvValues as $tvId => $value) {
                $tvId = (int) $tvId;
                if ($tvId <= 0) {
                    continue;
                }
                $this->setResourceTvValueInternal($resourceId, $tvId, $value);
            }
        });

        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'resource',
            'id' => $resourceId,
            'message' => 'Resource updated.',
            'regenerated' => $regenerated,
        ];
    }

    /** @return array<string,mixed> */
    public function deleteResource(int $resourceId): array
    {
        $contentTable = $this->requireTable('resources_table', 'site_content');
        $this->assertExists($contentTable, $resourceId, 'Resource');

        $update = [
            'deleted' => 1,
            'published' => 0,
        ];
        if (Schema::hasColumn($contentTable, 'deletedon')) {
            $update['deletedon'] = time();
        }
        if (Schema::hasColumn($contentTable, 'editedon')) {
            $update['editedon'] = time();
        }

        DB::table($contentTable)->where('id', $resourceId)->update($update);
        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'resource',
            'id' => $resourceId,
            'message' => 'Resource deleted.',
            'regenerated' => $regenerated,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function attachTvToTemplate(int $templateId, int $tvId, array $input = []): array
    {
        $templatesTable = $this->requireTable('templates_table', 'site_templates');
        $tvsTable = $this->requireTable('tvs_table', 'site_tmplvars');
        $pivotTable = $this->requireTable('template_tv_pivot_table', 'site_tmplvar_templates');
        $this->assertExists($templatesTable, $templateId, 'Template');
        $this->assertExists($tvsTable, $tvId, 'TV');

        $columns = $this->columnMap($pivotTable);
        $rank = (int) ($input['rank'] ?? 0);

        $where = [
            'templateid' => $templateId,
            'tmplvarid' => $tvId,
        ];

        if (isset($columns['rank'])) {
            DB::table($pivotTable)->updateOrInsert($where, ['rank' => $rank]);
        } else {
            DB::table($pivotTable)->updateOrInsert($where, []);
        }

        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'template_tv_link',
            'message' => 'TV attached to template.',
            'template_id' => $templateId,
            'tv_id' => $tvId,
            'regenerated' => $regenerated,
        ];
    }

    /** @return array<string,mixed> */
    public function detachTvFromTemplate(int $templateId, int $tvId): array
    {
        $pivotTable = $this->requireTable('template_tv_pivot_table', 'site_tmplvar_templates');

        $deleted = DB::table($pivotTable)
            ->where('templateid', $templateId)
            ->where('tmplvarid', $tvId)
            ->delete();

        if ($deleted === 0) {
            throw new RuntimeException('Template-TV link not found.');
        }

        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'template_tv_link',
            'message' => 'TV detached from template.',
            'template_id' => $templateId,
            'tv_id' => $tvId,
            'regenerated' => $regenerated,
        ];
    }

    /** @return array<string,mixed> */
    public function setResourceTemplate(int $resourceId, int $templateId): array
    {
        if ($templateId <= 0) {
            throw new RuntimeException('Template id must be greater than zero.');
        }

        $contentTable = $this->requireTable('resources_table', 'site_content');
        $templatesTable = $this->requireTable('templates_table', 'site_templates');
        $this->assertExists($contentTable, $resourceId, 'Resource');
        $this->assertExists($templatesTable, $templateId, 'Template');

        $update = [
            'template' => $templateId,
        ];
        if (Schema::hasColumn($contentTable, 'editedon')) {
            $update['editedon'] = time();
        }

        DB::table($contentTable)->where('id', $resourceId)->update($update);

        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'resource',
            'message' => 'Resource template updated.',
            'resource_id' => $resourceId,
            'template_id' => $templateId,
            'regenerated' => $regenerated,
        ];
    }

    /** @return array<string,mixed> */
    public function setResourcePublished(int $resourceId, bool $published): array
    {
        $contentTable = $this->requireTable('resources_table', 'site_content');
        $this->assertExists($contentTable, $resourceId, 'Resource');

        $timestamp = time();
        $update = [
            'published' => $published ? 1 : 0,
        ];
        if (Schema::hasColumn($contentTable, 'publishedon')) {
            $update['publishedon'] = $published ? $timestamp : 0;
        }
        if (Schema::hasColumn($contentTable, 'editedon')) {
            $update['editedon'] = $timestamp;
        }

        DB::table($contentTable)->where('id', $resourceId)->update($update);

        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'resource',
            'message' => $published ? 'Resource published.' : 'Resource unpublished.',
            'resource_id' => $resourceId,
            'published' => $published,
            'regenerated' => $regenerated,
        ];
    }

    /** @return array<string,mixed> */
    public function setResourceTvValue(int $resourceId, int $tvId, mixed $value): array
    {
        $this->setResourceTvValueInternal($resourceId, $tvId, $value);
        $regenerated = $this->regenerateRegistryIfNeeded();

        return [
            'entity' => 'tv_value',
            'message' => 'TV value saved for resource.',
            'resource_id' => $resourceId,
            'tv_id' => $tvId,
            'regenerated' => $regenerated,
        ];
    }

    private function setResourceTvValueInternal(int $resourceId, int $tvId, mixed $value): void
    {
        if ($resourceId <= 0) {
            throw new RuntimeException('Resource id must be greater than zero.');
        }
        if ($tvId <= 0) {
            throw new RuntimeException('TV id must be greater than zero.');
        }

        $contentTable = $this->requireTable('resources_table', 'site_content');
        $tvsTable = $this->requireTable('tvs_table', 'site_tmplvars');
        $valuesTable = $this->requireTable('tv_values_table', 'site_tmplvar_contentvalues');
        $this->assertExists($contentTable, $resourceId, 'Resource');
        $this->assertExists($tvsTable, $tvId, 'TV');
        $this->assertTvAttachedToResourceTemplate($resourceId, $tvId, $contentTable);

        DB::table($valuesTable)->updateOrInsert(
            [
                'contentid' => $resourceId,
                'tmplvarid' => $tvId,
            ],
            [
                'value' => is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );
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

        $output = $this->resolveOutputPath();
        $generator->writePayload($payload, $output, $format);

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

    private function assertTvAttachedToResourceTemplate(int $resourceId, int $tvId, string $contentTable): void
    {
        $pivotTable = $this->requireTable('template_tv_pivot_table', 'site_tmplvar_templates');
        $resource = DB::table($contentTable)
            ->select(['id', 'template'])
            ->where('id', $resourceId)
            ->first();

        $templateId = (int) ($resource->template ?? 0);
        if ($templateId <= 0) {
            throw new RuntimeException('Resource has no valid template for TV assignment.');
        }

        $attached = DB::table($pivotTable)
            ->where('templateid', $templateId)
            ->where('tmplvarid', $tvId)
            ->exists();

        if (!$attached) {
            throw new RuntimeException('TV is not attached to resource template.');
        }
    }

    private function toAbsolutePath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        $basePath = function_exists('base_path') ? (string) base_path() : (getcwd() ?: '');
        return rtrim(dirname($basePath), '/') . '/' . ltrim($path, '/');
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

    /** @return array<string,bool> */
    private function columnMap(string $table): array
    {
        $map = [];
        foreach (Schema::getColumnListing($table) as $column) {
            $map[(string) $column] = true;
        }

        return $map;
    }

    /** @param array<string,mixed> $payload @param array<string,bool> $columns */
    private function assignIfColumn(array &$payload, array $columns, string $column, mixed $value): void
    {
        if (isset($columns[$column])) {
            $payload[$column] = $value;
        }
    }

    private function assertExists(string $table, int $id, string $label): void
    {
        if ($id <= 0) {
            throw new RuntimeException($label . ' id must be greater than zero.');
        }

        $exists = DB::table($table)->where('id', $id)->exists();
        if (!$exists) {
            throw new RuntimeException($label . ' not found.');
        }
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

    private function boolInt(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? 1 : 0;
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
