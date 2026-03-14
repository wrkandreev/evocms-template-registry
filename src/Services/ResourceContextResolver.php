<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ResourceContextResolver
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function resolve(array $payload, mixed $resourceId, mixed $url): array
    {
        $contentTable = $this->resolveTableName((string) $this->cfg('resources_table', 'site_content'));
        $valuesTable = $this->resolveTableName((string) $this->cfg('tv_values_table', 'site_tmplvar_contentvalues'));

        if ($contentTable === null || $valuesTable === null) {
            throw new RuntimeException('Required resource tables not found (site_content/site_tmplvar_contentvalues).');
        }

        $resource = $this->findResource($contentTable, $resourceId, $url);
        if ($resource === null) {
            return [
                'message' => 'Resource not found.',
                'code' => 'resource_not_found',
                'status' => 404,
            ];
        }

        $templateId = (int) ($resource->template ?? 0);
        $template = $this->findTemplate((array) ($payload['templates'] ?? []), $templateId);

        $availableTvs = $this->resolveAvailableTvs($payload, $template);
        $tvValues = $this->loadTvValues($valuesTable, (int) $resource->id, $availableTvs);

        return [
            'resource' => [
                'id' => (int) ($resource->id ?? 0),
                'pagetitle' => (string) ($resource->pagetitle ?? ''),
                'longtitle' => (string) ($resource->longtitle ?? ''),
                'alias' => (string) ($resource->alias ?? ''),
                'uri' => (string) ($resource->uri ?? ''),
                'template_id' => $templateId,
            ],
            'template' => $template,
            'tvs_available' => $availableTvs,
            'tv_values' => $tvValues,
            'stats' => [
                'available_tvs' => count($availableTvs),
                'tv_values_found' => count($tvValues),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    public function listResources(array $payload, int $limit = 100): array
    {
        $contentTable = $this->resolveTableName((string) $this->cfg('resources_table', 'site_content'));
        if ($contentTable === null) {
            throw new RuntimeException('Required resource table not found (site_content).');
        }

        $columns = $this->resourceListColumns($contentTable);

        $rows = DB::table($contentTable)
            ->select($columns)
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $templatesById = [];
        foreach ((array) ($payload['templates'] ?? []) as $template) {
            if (!is_array($template)) {
                continue;
            }

            $templateId = (int) ($template['id'] ?? 0);
            if ($templateId > 0) {
                $templatesById[$templateId] = $template;
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $templateId = (int) ($row->template ?? 0);
            $template = $templatesById[$templateId] ?? null;

            $result[] = [
                'id' => (int) ($row->id ?? 0),
                'type' => (string) ($row->type ?? ''),
                'content_type' => (string) ($row->contentType ?? ''),
                'pagetitle' => (string) ($row->pagetitle ?? ''),
                'longtitle' => (string) ($row->longtitle ?? ''),
                'description' => (string) ($row->description ?? ''),
                'alias' => (string) ($row->alias ?? ''),
                'link_attributes' => (string) ($row->link_attributes ?? ''),
                'uri' => (string) ($row->uri ?? ''),
                'introtext' => (string) ($row->introtext ?? ''),
                'template_id' => $templateId,
                'template_name' => is_array($template) ? (string) ($template['name'] ?? '') : '',
                'template_alias' => is_array($template) ? (string) ($template['alias'] ?? '') : '',
                'menuindex' => isset($row->menuindex) ? (int) $row->menuindex : null,
                'published' => isset($row->published) ? (bool) $row->published : null,
                'pub_date' => isset($row->pub_date) ? (int) $row->pub_date : null,
                'unpub_date' => isset($row->unpub_date) ? (int) $row->unpub_date : null,
                'deleted' => isset($row->deleted) ? (bool) $row->deleted : null,
                'isfolder' => isset($row->isfolder) ? (bool) $row->isfolder : null,
                'parent' => isset($row->parent) ? (int) $row->parent : null,
                'richtext' => isset($row->richtext) ? (bool) $row->richtext : null,
                'searchable' => isset($row->searchable) ? (bool) $row->searchable : null,
                'cacheable' => isset($row->cacheable) ? (bool) $row->cacheable : null,
                'createdon' => isset($row->createdon) ? (int) $row->createdon : null,
                'editedon' => isset($row->editedon) ? (int) $row->editedon : null,
                'deletedon' => isset($row->deletedon) ? (int) $row->deletedon : null,
                'publishedon' => isset($row->publishedon) ? (int) $row->publishedon : null,
                'menutitle' => (string) ($row->menutitle ?? ''),
                'hide_from_tree' => isset($row->hide_from_tree) ? (bool) $row->hide_from_tree : null,
                'privateweb' => isset($row->privateweb) ? (bool) $row->privateweb : null,
                'privatemgr' => isset($row->privatemgr) ? (bool) $row->privatemgr : null,
                'content_dispo' => isset($row->content_dispo) ? (int) $row->content_dispo : null,
                'hidemenu' => isset($row->hidemenu) ? (bool) $row->hidemenu : null,
                'alias_visible' => isset($row->alias_visible) ? (bool) $row->alias_visible : null,
            ];
        }

        return $result;
    }

    /** @return array<int,string> */
    private function resourceListColumns(string $contentTable): array
    {
        $candidates = [
            'id',
            'type',
            'contentType',
            'pagetitle',
            'longtitle',
            'description',
            'alias',
            'link_attributes',
            'published',
            'pub_date',
            'unpub_date',
            'parent',
            'isfolder',
            'introtext',
            'richtext',
            'template',
            'menuindex',
            'searchable',
            'cacheable',
            'createdon',
            'editedon',
            'deleted',
            'deletedon',
            'publishedon',
            'menutitle',
            'hide_from_tree',
            'privateweb',
            'privatemgr',
            'content_dispo',
            'hidemenu',
            'alias_visible',
            'uri',
        ];

        $result = [];
        foreach ($candidates as $column) {
            if (Schema::hasColumn($contentTable, $column)) {
                $result[] = $column;
            }
        }

        return $result;
    }

    private function findResource(string $contentTable, mixed $resourceId, mixed $url): ?object
    {
        $hasUriColumn = Schema::hasColumn($contentTable, 'uri');
        $columns = ['id', 'pagetitle', 'longtitle', 'alias', 'template'];
        if ($hasUriColumn) {
            $columns[] = 'uri';
        }

        if ($resourceId !== null && $resourceId !== '') {
            $id = (int) $resourceId;
            if ($id > 0) {
                return DB::table($contentTable)
                    ->select($columns)
                    ->where('id', $id)
                    ->first();
            }
        }

        $normalizedPath = $this->normalizeUrlPath((string) ($url ?? ''));
        if ($normalizedPath === null) {
            return null;
        }

        $uriCandidates = $this->buildUriCandidates($normalizedPath);

        if ($hasUriColumn) {
            $query = DB::table($contentTable)
                ->select($columns)
                ->where(function ($q) use ($uriCandidates) {
                    foreach ($uriCandidates as $candidate) {
                        $q->orWhere('uri', $candidate);
                    }
                })
                ->orderByRaw('LENGTH(uri) DESC')
                ->orderBy('id');

            $resource = $query->first();
            if ($resource !== null) {
                return $resource;
            }
        }

        $singleSegmentAlias = $this->extractSingleSegmentAlias($normalizedPath);
        if ($singleSegmentAlias !== null) {
            return DB::table($contentTable)
                ->select($columns)
                ->where('alias', $singleSegmentAlias)
                ->orderBy('id')
                ->first();
        }

        return null;
    }

    /** @param array<int,array<string,mixed>> $templates */
    private function findTemplate(array $templates, int $templateId): ?array
    {
        foreach ($templates as $template) {
            if ((int) ($template['id'] ?? 0) === $templateId) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed>|null $template
     * @return array<int,array<string,mixed>>
     */
    private function resolveAvailableTvs(array $payload, ?array $template): array
    {
        if ($template === null) {
            return [];
        }

        $catalog = [];
        foreach ((array) ($payload['tv_catalog'] ?? []) as $tv) {
            $tvId = (int) ($tv['id'] ?? 0);
            if ($tvId > 0) {
                $catalog[$tvId] = $tv;
            }
        }

        $result = [];
        foreach ((array) ($template['tv_refs'] ?? []) as $ref) {
            $tvId = (int) ($ref['id'] ?? 0);
            if ($tvId <= 0) {
                continue;
            }

            $item = [
                'id' => $tvId,
                'rank' => isset($ref['rank']) ? (int) $ref['rank'] : null,
            ];

            $meta = $catalog[$tvId] ?? null;
            if (is_array($meta)) {
                $item['name'] = (string) ($meta['name'] ?? '');
                $item['caption'] = (string) ($meta['caption'] ?? '');
                $item['type'] = (string) ($meta['type'] ?? '');
                $item['default_text'] = (string) ($meta['default_text'] ?? '');
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<int,array<string,mixed>> $availableTvs
     * @return array<int,array<string,mixed>>
     */
    private function loadTvValues(string $valuesTable, int $resourceId, array $availableTvs): array
    {
        if ($resourceId <= 0 || $availableTvs === []) {
            return [];
        }

        $allowedIds = [];
        $metaById = [];
        foreach ($availableTvs as $tv) {
            $tvId = (int) ($tv['id'] ?? 0);
            if ($tvId > 0) {
                $allowedIds[] = $tvId;
                $metaById[$tvId] = $tv;
            }
        }

        if ($allowedIds === []) {
            return [];
        }

        $rows = DB::table($valuesTable)
            ->select(['tmplvarid', 'value'])
            ->where('contentid', $resourceId)
            ->whereIn('tmplvarid', $allowedIds)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $tvId = (int) ($row->tmplvarid ?? 0);
            if ($tvId <= 0) {
                continue;
            }

            $meta = $metaById[$tvId] ?? [];
            $result[] = [
                'id' => $tvId,
                'name' => (string) ($meta['name'] ?? ''),
                'caption' => (string) ($meta['caption'] ?? ''),
                'type' => (string) ($meta['type'] ?? ''),
                'value' => (string) ($row->value ?? ''),
            ];
        }

        return $result;
    }

    private function normalizeUrlPath(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        $parsedPath = parse_url($trimmed, PHP_URL_PATH);
        $path = is_string($parsedPath) ? $parsedPath : $trimmed;
        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /** @return array<int,string> */
    private function buildUriCandidates(string $path): array
    {
        if ($path === '/') {
            return ['', '/', 'index', 'index.html'];
        }

        $withoutLeadingSlash = ltrim($path, '/');
        return [
            $withoutLeadingSlash,
            $withoutLeadingSlash . '/',
            $path,
            $path . '/',
            $withoutLeadingSlash . '.html',
        ];
    }

    private function extractSingleSegmentAlias(string $path): ?string
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '' || str_contains($trimmed, '/')) {
            return null;
        }

        return $trimmed;
    }

    private function resolveTableName(string $base): ?string
    {
        if (Schema::hasTable($base)) {
            return $base;
        }

        $defaultConnection = (string) config('database.default');
        $prefix = (string) config("database.connections.{$defaultConnection}.prefix", '');
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
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }
}
