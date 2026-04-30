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

        $match = $this->findResourceMatch($contentTable, $resourceId, $url);
        if ($match === null) {
            return [
                'message' => 'Resource not found.',
                'code' => 'resource_not_found',
                'status' => 404,
            ];
        }

        $resource = $match['resource'];

        $templateId = (int) ($resource->template ?? 0);
        $template = $this->findTemplate((array) ($payload['templates'] ?? []), $templateId);

        $availableTvs = $this->resolveAvailableTvs($payload, $template);
        $tvValues = $this->loadTvValues($valuesTable, (int) $resource->id, $availableTvs);

        return [
            'resource' => [
                'id' => (int) ($resource->id ?? 0),
                'pagetitle' => (string) ($resource->pagetitle ?? ''),
                'longtitle' => (string) ($resource->longtitle ?? ''),
                'menutitle' => (string) ($resource->menutitle ?? ''),
                'description' => (string) ($resource->description ?? ''),
                'introtext' => (string) ($resource->introtext ?? ''),
                'alias' => (string) ($resource->alias ?? ''),
                'uri' => (string) ($resource->uri ?? ''),
                'template_id' => $templateId,
            ],
            'resolved' => [
                'normalized_url' => $match['normalized_url'],
                'matched_by' => $match['matched_by'],
            ],
            'template' => $template,
            'blang' => $this->resolveBLangContext((array) ($payload['blang'] ?? []), $templateId, $resource),
            'tvs_available' => $availableTvs,
            'tv_values' => $tvValues,
            'stats' => [
                'available_tvs' => count($availableTvs),
                'tv_values_found' => count($tvValues),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function resolveResourceId(mixed $resourceId, mixed $url): array
    {
        $contentTable = $this->resolveTableName((string) $this->cfg('resources_table', 'site_content'));
        if ($contentTable === null) {
            throw new RuntimeException('Required resource table not found (site_content).');
        }

        $match = $this->findResourceMatch($contentTable, $resourceId, $url);
        if ($match === null) {
            return [
                'message' => 'Resource not found.',
                'code' => 'resource_not_found',
                'status' => 404,
            ];
        }

        $resource = $match['resource'];

        return [
            'resource_id' => (int) ($resource->id ?? 0),
            'normalized_url' => $match['normalized_url'],
            'matched_by' => $match['matched_by'],
            'resource' => [
                'id' => (int) ($resource->id ?? 0),
                'pagetitle' => (string) ($resource->pagetitle ?? ''),
                'alias' => (string) ($resource->alias ?? ''),
                'uri' => (string) ($resource->uri ?? ''),
                'template_id' => (int) ($resource->template ?? 0),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    public function listResources(array $payload, int $limit = 100, bool $includeDeleted = false): array
    {
        $contentTable = $this->resolveTableName((string) $this->cfg('resources_table', 'site_content'));
        if ($contentTable === null) {
            throw new RuntimeException('Required resource table not found (site_content).');
        }

        $columns = $this->resourceListColumns($contentTable);

        $query = DB::table($contentTable)
            ->select($columns)
            ->orderBy('id')
            ->limit(max(1, $limit));

        if (!$includeDeleted && Schema::hasColumn($contentTable, 'deleted')) {
            $query->where('deleted', 0);
        }

        $rows = $query->get();

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

        return $this->mapResourceRows($rows, $templatesById);
    }

    public function countResources(bool $includeDeleted = false): int
    {
        $contentTable = $this->resolveTableName((string) $this->cfg('resources_table', 'site_content'));
        if ($contentTable === null) {
            throw new RuntimeException('Required resource table not found (site_content).');
        }

        $query = DB::table($contentTable);
        if (!$includeDeleted && Schema::hasColumn($contentTable, 'deleted')) {
            $query->where('deleted', 0);
        }

        return (int) $query->count();
    }

    /** @param array<string,mixed> $payload @return array<string,mixed>|null */
    public function resourceById(array $payload, int $resourceId, bool $includeDeleted = false): ?array
    {
        if ($resourceId <= 0) {
            return null;
        }

        $contentTable = $this->resolveTableName((string) $this->cfg('resources_table', 'site_content'));
        if ($contentTable === null) {
            throw new RuntimeException('Required resource table not found (site_content).');
        }

        $columns = $this->resourceListColumns($contentTable);
        $query = DB::table($contentTable)
            ->select($columns)
            ->where('id', $resourceId);

        if (!$includeDeleted && Schema::hasColumn($contentTable, 'deleted')) {
            $query->where('deleted', 0);
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        $templatesById = $this->templatesById($payload);
        $result = $this->mapResourceRows([$row], $templatesById);

        return $result[0] ?? null;
    }

    /** @param array<string,mixed> $payload @return array<int,array<string,mixed>> */
    public function childResources(array $payload, int $parentId, int $limit = 100, bool $includeDeleted = false): array
    {
        if ($parentId <= 0) {
            return [];
        }

        $contentTable = $this->resolveTableName((string) $this->cfg('resources_table', 'site_content'));
        if ($contentTable === null) {
            throw new RuntimeException('Required resource table not found (site_content).');
        }

        $columns = $this->resourceListColumns($contentTable);
        $query = DB::table($contentTable)
            ->select($columns)
            ->where('parent', $parentId)
            ->orderBy('id')
            ->limit(max(1, $limit));

        if (!$includeDeleted && Schema::hasColumn($contentTable, 'deleted')) {
            $query->where('deleted', 0);
        }

        $rows = $query->get();

        return $this->mapResourceRows($rows, $this->templatesById($payload));
    }

    public function countChildResources(int $parentId, bool $includeDeleted = false): int
    {
        if ($parentId <= 0) {
            return 0;
        }

        $contentTable = $this->resolveTableName((string) $this->cfg('resources_table', 'site_content'));
        if ($contentTable === null) {
            throw new RuntimeException('Required resource table not found (site_content).');
        }

        $query = DB::table($contentTable)->where('parent', $parentId);
        if (!$includeDeleted && Schema::hasColumn($contentTable, 'deleted')) {
            $query->where('deleted', 0);
        }

        return (int) $query->count();
    }

    /** @param array<string,mixed> $payload @return array<int,array<string,mixed>> */
    private function templatesById(array $payload): array
    {
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

        return $templatesById;
    }

    /** @param iterable<object> $rows @param array<int,array<string,mixed>> $templatesById @return array<int,array<string,mixed>> */
    private function mapResourceRows(iterable $rows, array $templatesById): array
    {
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

    /** @return array{resource:object,matched_by:string,normalized_url:?string}|null */
    private function findResourceMatch(string $contentTable, mixed $resourceId, mixed $url): ?array
    {
        $hasUriColumn = Schema::hasColumn($contentTable, 'uri');
        $columns = ['id', 'pagetitle', 'longtitle', 'menutitle', 'description', 'introtext', 'alias', 'template'];
        if ($hasUriColumn) {
            $columns[] = 'uri';
        }

        if ($resourceId !== null && $resourceId !== '') {
            $id = (int) $resourceId;
            if ($id > 0) {
                $resource = DB::table($contentTable)
                    ->select($columns)
                    ->where('id', $id)
                    ->first();
                if ($resource !== null) {
                    return [
                        'resource' => $resource,
                        'matched_by' => 'id',
                        'normalized_url' => null,
                    ];
                }
            }
        }

        $normalizedPath = $this->normalizeUrlPath((string) ($url ?? ''));
        if ($normalizedPath === null) {
            return null;
        }

        $uriCandidates = $this->buildUriCandidates($normalizedPath);

        if ($hasUriColumn) {
            foreach ($uriCandidates as $candidate) {
                $resource = $this->selectResourceByUri($contentTable, $columns, $candidate);
                if ($resource !== null) {
                    return [
                        'resource' => $resource,
                        'matched_by' => $this->classifyUriMatch($normalizedPath, $candidate),
                        'normalized_url' => $normalizedPath,
                    ];
                }
            }
        }

        $singleSegmentAlias = $this->extractSingleSegmentAlias($normalizedPath);
        if ($singleSegmentAlias !== null) {
            $resource = DB::table($contentTable)
                ->select($columns)
                ->where('alias', $singleSegmentAlias)
                ->orderBy('id')
                ->first();
            if ($resource !== null) {
                return [
                    'resource' => $resource,
                    'matched_by' => 'alias',
                    'normalized_url' => $normalizedPath,
                ];
            }
        }

        if ($normalizedPath === '/') {
            $home = $this->findHomeResource($contentTable, $columns, $hasUriColumn);
            if ($home !== null) {
                return [
                    'resource' => $home,
                    'matched_by' => 'site_start',
                    'normalized_url' => $normalizedPath,
                ];
            }
        }

        return null;
    }

    /** @param array<int,string> $columns */
    private function selectResourceByUri(string $contentTable, array $columns, string $candidate): ?object
    {
        return DB::table($contentTable)
            ->select($columns)
            ->where('uri', $candidate)
            ->orderByRaw('LENGTH(uri) DESC')
            ->orderBy('id')
            ->first();
    }

    private function classifyUriMatch(string $normalizedPath, string $candidate): string
    {
        $normalizedCandidate = '/' . ltrim($candidate, '/');
        if ($normalizedPath === $normalizedCandidate) {
            return 'uri';
        }

        if (str_ends_with($normalizedPath, '.html')) {
            $withoutHtml = preg_replace('/\.html$/i', '', $normalizedPath) ?: $normalizedPath;
            if ($withoutHtml === $normalizedCandidate || ($withoutHtml . '/') === $normalizedCandidate) {
                return 'uri_html';
            }
        }

        return 'uri';
    }

    /** @param array<int,string> $columns */
    private function findHomeResource(string $contentTable, array $columns, bool $hasUriColumn): ?object
    {
        $siteStartId = $this->resolveSiteStartId();
        if ($siteStartId !== null) {
            $resource = DB::table($contentTable)
                ->select($columns)
                ->where('id', $siteStartId)
                ->first();
            if ($resource !== null) {
                return $resource;
            }
        }

        if ($hasUriColumn) {
            $resource = DB::table($contentTable)
                ->select($columns)
                ->whereIn('uri', ['', '/', 'index', 'index.html'])
                ->orderBy('id')
                ->first();
            if ($resource !== null) {
                return $resource;
            }
        }

        $query = DB::table($contentTable)->select($columns);
        if (Schema::hasColumn($contentTable, 'deleted')) {
            $query->where('deleted', 0);
        }
        if (Schema::hasColumn($contentTable, 'published')) {
            $query->where('published', 1);
        }
        if (Schema::hasColumn($contentTable, 'parent')) {
            $query->where('parent', 0);
        }
        if (Schema::hasColumn($contentTable, 'isfolder')) {
            $query->where('isfolder', 0);
        }
        if (Schema::hasColumn($contentTable, 'menuindex')) {
            $query->orderBy('menuindex');
        }

        return $query->orderBy('id')->first();
    }

    private function resolveSiteStartId(): ?int
    {
        if (function_exists('evolutionCMS')) {
            try {
                $siteStart = (int) \evolutionCMS()->getConfig('site_start');
                if ($siteStart > 0) {
                    return $siteStart;
                }
            } catch (\Throwable $e) {
            }
        }

        $siteStartFromConfig = (int) \config('site_start', 0);
        return $siteStartFromConfig > 0 ? $siteStartFromConfig : null;
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
        $candidates = [
            $withoutLeadingSlash,
            $withoutLeadingSlash . '/',
            $path,
            $path . '/',
        ];

        if (!str_ends_with(strtolower($withoutLeadingSlash), '.html')) {
            $candidates[] = $withoutLeadingSlash . '.html';
        } else {
            $withoutHtml = preg_replace('/\.html$/i', '', $withoutLeadingSlash) ?: $withoutLeadingSlash;
            $candidates[] = $withoutHtml;
            $candidates[] = $withoutHtml . '/';
        }

        return array_values(array_unique($candidates));
    }

    private function extractSingleSegmentAlias(string $path): ?string
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '' || str_contains($trimmed, '/')) {
            return null;
        }

        $trimmed = preg_replace('/\.html$/i', '', $trimmed) ?: $trimmed;

        return $trimmed;
    }

    /** @param array<string,mixed> $blangPayload @return array<string,mixed> */
    private function resolveBLangContext(array $blangPayload, int $templateId, object $resource): array
    {
        $enabled = !empty($blangPayload['exists']);
        $languages = array_values(array_filter((array) ($blangPayload['languages'] ?? []), static fn (mixed $lang): bool => is_string($lang) && trim($lang) !== ''));
        $suffixes = is_array($blangPayload['suffixes'] ?? null) ? $blangPayload['suffixes'] : [];
        $templateFields = [];

        $allowedFieldIds = [];
        foreach ((array) ($blangPayload['template_links'] ?? []) as $link) {
            if (!is_array($link) || (int) ($link['template_id'] ?? 0) !== $templateId) {
                continue;
            }

            $fieldId = (int) ($link['field_id'] ?? 0);
            if ($fieldId > 0) {
                $allowedFieldIds[$fieldId] = (int) ($link['rank'] ?? 0);
            }
        }

        foreach ((array) ($blangPayload['fields_catalog'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fieldId = (int) ($field['id'] ?? 0);
            if ($fieldId <= 0 || !array_key_exists($fieldId, $allowedFieldIds)) {
                continue;
            }

            $baseName = (string) ($field['name'] ?? '');
            $localizedNames = [];
            $resourceValues = [];
            $baseResourceValue = property_exists($resource, $baseName)
                ? (string) ($resource->{$baseName} ?? '')
                : '';
            foreach ($languages as $language) {
                $resolvedName = $baseName . (string) ($suffixes[$language] ?? '');
                $localizedNames[$language] = $resolvedName;
                if (property_exists($resource, $resolvedName)) {
                    $resourceValues[$language] = (string) ($resource->{$resolvedName} ?? '');
                }
            }

            $templateFields[] = [
                'id' => $fieldId,
                'name' => $baseName,
                'caption' => (string) ($field['caption'] ?? ''),
                'type' => (string) ($field['type'] ?? ''),
                'tab' => (string) ($field['tab'] ?? ''),
                'rank' => $allowedFieldIds[$fieldId],
                'base_resource_value' => $baseResourceValue,
                'localized_names' => $localizedNames,
                'resource_values' => $resourceValues,
            ];
        }

        usort($templateFields, static function (array $left, array $right): int {
            $rankCompare = ((int) ($left['rank'] ?? 0)) <=> ((int) ($right['rank'] ?? 0));
            if ($rankCompare !== 0) {
                return $rankCompare;
            }

            return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
        });

        return [
            'enabled' => $enabled,
            'default_language' => (string) ($blangPayload['default_language'] ?? ''),
            'languages' => $languages,
            'suffixes' => $suffixes,
            'settings' => (array) ($blangPayload['settings'] ?? []),
            'template_fields' => $templateFields,
            'stats' => [
                'template_fields_total' => count($templateFields),
            ],
        ];
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
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }
}
