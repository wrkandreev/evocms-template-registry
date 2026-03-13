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

    private function findResource(string $contentTable, mixed $resourceId, mixed $url): ?object
    {
        if ($resourceId !== null && $resourceId !== '') {
            $id = (int) $resourceId;
            if ($id > 0) {
                return DB::table($contentTable)
                    ->select(['id', 'pagetitle', 'longtitle', 'alias', 'uri', 'template'])
                    ->where('id', $id)
                    ->first();
            }
        }

        $normalizedPath = $this->normalizeUrlPath((string) ($url ?? ''));
        if ($normalizedPath === null) {
            return null;
        }

        $uriCandidates = $this->buildUriCandidates($normalizedPath);

        $query = DB::table($contentTable)
            ->select(['id', 'pagetitle', 'longtitle', 'alias', 'uri', 'template'])
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

        $singleSegmentAlias = $this->extractSingleSegmentAlias($normalizedPath);
        if ($singleSegmentAlias !== null) {
            return DB::table($contentTable)
                ->select(['id', 'pagetitle', 'longtitle', 'alias', 'uri', 'template'])
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
