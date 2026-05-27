<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Http\Controllers;

use Illuminate\Http\Request;
use RuntimeException;
use WrkAndreev\EvocmsTemplateRegistry\Services\BLangHealthService;
use WrkAndreev\EvocmsTemplateRegistry\Services\BLangLexiconService;
use WrkAndreev\EvocmsTemplateRegistry\Services\PageBuilderConfigExtractor;
use WrkAndreev\EvocmsTemplateRegistry\Services\ResourceContextResolver;
use WrkAndreev\EvocmsTemplateRegistry\Services\TemplateRegistryGenerator;

class TemplateRegistryApiController
{
    public function index(Request $request)
    {
        try {
            $payload = $this->payload();
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        $templateId = $request->query('template_id');
        if ($templateId !== null && $templateId !== '') {
            $id = (int) $templateId;
            foreach ((array) ($payload['templates'] ?? []) as $template) {
                if ((int) ($template['id'] ?? 0) === $id) {
                    return \response()->json($template);
                }
            }

            return \response()->json([
                'message' => 'Template not found.',
            ], 404);
        }

        return \response()->json($payload);
    }

    public function templates()
    {
        try {
            return \response()->json((array) ($this->payload()['templates'] ?? []));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function templateById(int $id)
    {
        try {
            $templates = (array) ($this->payload()['templates'] ?? []);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        foreach ($templates as $template) {
            if ((int) ($template['id'] ?? 0) === $id) {
                return \response()->json($template);
            }
        }

        return \response()->json([
            'message' => 'Template not found.',
        ], 404);
    }

    public function tvCatalog()
    {
        try {
            return \response()->json((array) ($this->payload()['tv_catalog'] ?? []));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function clientSettings()
    {
        try {
            return \response()->json((array) ($this->payload()['client_settings'] ?? []));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function resources(Request $request)
    {
        try {
            $payload = $this->payload();
            $config = (array) \config('template-registry', []);
            $resolver = new ResourceContextResolver($config);
            $limit = $this->resolveResourceLimit($request, $resolver, false);
            $includeDeleted = filter_var($request->query('include_deleted', false), FILTER_VALIDATE_BOOL);
            $items = $resolver->listResources($payload, $limit, $includeDeleted);
            $total = $resolver->countResources($includeDeleted);

            return $this->resourceListResponse($items, $total, $limit, $request);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function resourceById(int $id, Request $request)
    {
        try {
            $payload = $this->payload();
            $config = (array) \config('template-registry', []);
            $resolver = new ResourceContextResolver($config);
            $includeDeleted = filter_var($request->query('include_deleted', false), FILTER_VALIDATE_BOOL);
            $resource = $resolver->resourceById($payload, $id, $includeDeleted);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        if ($resource === null) {
            return \response()->json([
                'message' => 'Resource not found.',
            ], 404);
        }

        return \response()->json($resource);
    }

    public function resourceChildren(int $id, Request $request)
    {
        try {
            $payload = $this->payload();
            $config = (array) \config('template-registry', []);
            $resolver = new ResourceContextResolver($config);
            $limit = $this->resolveResourceLimit($request, $resolver, true, $id);
            $includeDeleted = filter_var($request->query('include_deleted', false), FILTER_VALIDATE_BOOL);
            $resources = $resolver->childResources($payload, $id, $limit, $includeDeleted);
            $total = $resolver->countChildResources($id, $includeDeleted);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        return $this->resourceListResponse($resources, $total, $limit, $request);
    }

    public function stats()
    {
        try {
            return \response()->json((array) ($this->payload()['stats'] ?? []));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function resourceResolve(Request $request)
    {
        try {
            $config = (array) \config('template-registry', []);
            $resolver = new ResourceContextResolver($config);
            $result = $resolver->resolveResourceId(
                $request->query('resource_id'),
                $request->query('url')
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return \response()->json($result, $status);
    }

    public function resourceContext(Request $request)
    {
        try {
            $payload = $this->payload();
            $config = (array) \config('template-registry', []);
            $resolver = new ResourceContextResolver($config);
            $context = $resolver->resolve(
                $payload,
                $request->query('resource_id'),
                $request->query('url')
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        $status = (int) ($context['status'] ?? 200);
        unset($context['status']);

        return \response()->json($context, $status);
    }

    public function pageBuilderConfigs()
    {
        try {
            $config = (array) \config('template-registry', []);
            $extractor = new PageBuilderConfigExtractor($config, $this->projectRoot());
            return \response()->json($extractor->extract());
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function blang()
    {
        try {
            return \response()->json((array) ($this->payload()['blang'] ?? []));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function blangLexicon(Request $request)
    {
        try {
            $config = (array) \config('template-registry', []);
            $service = new BLangLexiconService($config);
            $limit = (int) $request->query('limit', 500);
            return \response()->json($service->listEntries($limit));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function blangHealth()
    {
        try {
            $service = new BLangHealthService((array) \config('template-registry', []));
            return \response()->json($service->health());
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function agentManifest()
    {
        $config = (array) \config('template-registry', []);
        $version = (string) ($config['version'] ?? '1.0.0');
        $apiConfig = (array) ($config['api'] ?? []);
        $prefix = trim((string) ($apiConfig['prefix'] ?? 'api/template-registry'), '/');
        $writeEnabled = (bool) ($apiConfig['write_enabled'] ?? false);
        $regenerateAfterWrite = (bool) ($apiConfig['regenerate_after_write'] ?? true);

        $endpoints = [
            'read' => [
                'GET /' => ['description' => 'Full registry payload (templates, TVs, client_settings, blang, stats, system_features)', 'filter' => '?template_id=N'],
                'GET /templates' => ['description' => 'All templates from registry'],
                'GET /templates/{id}' => ['description' => 'Single template by ID'],
                'GET /tvs' => ['description' => 'Full TV catalog from site_tmplvars'],
                'GET /client-settings' => ['description' => 'ClientSettings normalized schema and current values'],
                'GET /resources' => ['description' => 'Resource list (paginated, default 100, max 500)', 'params' => '?limit=N&per_page=N&all=1&include_deleted=1&include_meta=1'],
                'GET /resources/{id}' => ['description' => 'Single resource with template meta and TV values'],
                'GET /resources/{id}/children' => ['description' => 'Child resources of given parent'],
                'GET /stats' => ['description' => 'Registry statistics'],
                'GET /resource-resolve' => ['description' => 'Resolve resource ID by URL or resource_id', 'params' => '?url=/path&resource_id=N'],
                'GET /resource-context' => ['description' => 'Full page context: resource meta, template, TVs, TV values', 'params' => '?url=/path&resource_id=N'],
                'GET /blang' => ['description' => 'bLang languages, settings, fields catalog, template links'],
                'GET /blang/health' => ['description' => 'bLang drift detection between template links and TV assignments'],
                'GET /blang/lexicon' => ['description' => 'bLang dictionary entries', 'params' => '?limit=N'],
                'GET /pagebuilder-configs' => ['description' => 'All PageBuilder config files parsed'],
                'GET /pagebuilder-configs/{name}' => ['description' => 'Single PageBuilder config by file name'],
                'GET /agent-manifest' => ['description' => 'This document — agent instructions for working with the API'],
            ],
            'write' => $writeEnabled ? [
                'POST /templates' => ['description' => 'Create template', 'returns_warnings' => true],
                'PATCH /templates/{templateId}' => ['description' => 'Update template', 'returns_warnings' => true],
                'DELETE /templates/{templateId}' => ['description' => 'Delete template'],
                'POST /tvs' => ['description' => 'Create TV'],
                'PATCH /tvs/{tvId}' => ['description' => 'Update TV'],
                'DELETE /tvs/{tvId}' => ['description' => 'Delete TV'],
                'PUT /templates/{templateId}/tvs/{tvId}' => ['description' => 'Attach TV to template'],
                'DELETE /templates/{templateId}/tvs/{tvId}' => ['description' => 'Detach TV from template'],
                'PATCH /client-settings' => ['description' => 'Update ClientSettings field values (schema-bound)'],
                'POST /resources' => ['description' => 'Create resource'],
                'PATCH /resources/{resourceId}' => ['description' => 'Update resource'],
                'DELETE /resources/{resourceId}' => ['description' => 'Delete resource (soft)'],
                'PUT /resources/{resourceId}/restore' => ['description' => 'Restore soft-deleted resource'],
                'PUT /resources/{resourceId}/template' => ['description' => 'Change resource template'],
                'PUT /resources/{resourceId}/published' => ['description' => 'Set resource published state'],
                'PUT /resources/{resourceId}/tv-values' => ['description' => 'Set multiple TV values at once'],
                'PUT /resources/{resourceId}/tv-values/{tvId}' => ['description' => 'Set single TV value'],
                'PATCH /resources/{resourceId}/blang-fields' => ['description' => 'Update resource bLang field values'],
                'POST /blang/lexicon' => ['description' => 'Create lexicon entry'],
                'PATCH /blang/lexicon/{entryId}' => ['description' => 'Update lexicon entry'],
                'DELETE /blang/lexicon/{entryId}' => ['description' => 'Delete lexicon entry'],
                'POST /blang/fields' => ['description' => 'Create bLang field'],
                'PATCH /blang/fields/{fieldId}' => ['description' => 'Update bLang field'],
                'DELETE /blang/fields/{fieldId}' => ['description' => 'Delete bLang field'],
                'POST /blang/default-params' => ['description' => 'Seed default bLang TVs from template fields'],
                'POST /blang/fix-template-links' => ['description' => 'Repair missing bLang template links'],
                'PATCH /blang/settings' => ['description' => 'Update bLang settings, sync language columns'],
                'DELETE /blang/languages/{language}' => ['description' => 'Remove bLang language'],
                'POST /cache/blade/clear' => ['description' => 'Clear blade cache files'],
            ] : [],
        ];

        return \response()->json([
            'name' => 'Evolution CMS Template Registry API',
            'version' => $version,
            'purpose' => 'Admin-side API for reading and editing templates, TVs, resources, bLang and ClientSettings in Evolution CMS',
            'base_url' => '/' . $prefix,
            'auth' => [
                'token_header' => 'X-Template-Registry-Token',
                'token_description' => 'Optional access token from config (api.access_token)',
                'write_token_header' => 'X-Template-Registry-Write-Token',
                'write_token_description' => 'Optional write token from config (api.write_access_token)',
                'manager_session' => 'API also accepts Evolution CMS manager session cookies when require_manager=true',
            ],
            'write_enabled' => $writeEnabled,
            'regenerate_after_write' => $regenerateAfterWrite,
            'recommended_flow' => [
                '1. Start with GET /stats to check registry health and get counts',
                '2. To work with a specific page, use GET /resource-resolve?url=/path to get resource_id',
                '3. Then GET /resource-context?url=/path for full context (template, TVs, values)',
                '4. Use GET /templates to browse available templates and their TV assignments',
                '5. Use GET /tvs for the full TV catalog',
                '6. For edits: GET /resource-context first, then use write endpoints',
            ],
            'safety_rules' => [
                'All read endpoints are safe and do not modify data',
                'Write endpoints require X-Template-Registry-Write-Token header or manager session',
                'Write endpoints regenerate registry files after successful operations',
                'TV value updates in PUT /resources/{id}/tv-values accept {values:{"tvIdOrName": value}}',
                'ClientSettings writes only accept fields present in GET /client-settings -> fields_catalog',
                'Resource list is paginated by default (100 items, max 500)',
                'Deleted resources are excluded by default; use include_deleted=1 to include them',
            ],
            'error_handling' => [
                'registry_unavailable (503)' => 'Database tables missing or registry cannot be built',
                '404' => 'Resource/template/TV not found',
                'Validation errors return 422 with field-level messages',
                'Write operations return {ok: bool, code: string, message: string}',
                'POST/PATCH /templates may return warnings for missing controller/view artifacts',
            ],
            'endpoints' => $endpoints,
            'see_also' => [
                'Generated registry files (JSON/MD/PHP) in the configured output directory',
                'Manager module at /template-registry-admin/access to toggle API on/off',
                'README.md and AGENTS.md for full documentation',
            ],
        ]);
    }

    public function pageBuilderConfigByName(string $name)
    {
        try {
            $config = (array) \config('template-registry', []);
            $extractor = new PageBuilderConfigExtractor($config, $this->projectRoot());
            $item = $extractor->findByName($name);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        if ($item === null) {
            return \response()->json([
                'message' => 'PageBuilder config not found.',
            ], 404);
        }

        return \response()->json($item);
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        $config = (array) \config('template-registry', []);
        $generator = new TemplateRegistryGenerator($config);
        return $generator->buildPayload();
    }

    private function projectRoot(): string
    {
        $basePath = function_exists('base_path') ? base_path() : getcwd();
        return dirname((string) $basePath);
    }

    private function errorResponse(string $message)
    {
        return \response()->json([
            'message' => $message,
            'code' => 'registry_unavailable',
        ], 503);
    }

    private function resolveResourceLimit(Request $request, ResourceContextResolver $resolver, bool $childrenOnly = false, int $parentId = 0): int
    {
        $includeDeleted = filter_var($request->query('include_deleted', false), FILTER_VALIDATE_BOOL);
        $all = filter_var($request->query('all', false), FILTER_VALIDATE_BOOL);

        if ($all) {
            return $childrenOnly
                ? max(1, $resolver->countChildResources($parentId, $includeDeleted))
                : max(1, $resolver->countResources($includeDeleted));
        }

        $rawLimit = $request->query('limit', $request->query('per_page', 100));

        return max(1, min((int) $rawLimit, 500));
    }

    /** @param array<int,array<string,mixed>> $items */
    private function resourceListResponse(array $items, int $total, int $limit, Request $request)
    {
        $returned = count($items);
        $meta = [
            'total' => $total,
            'returned' => $returned,
            'limit' => $limit,
            'has_more' => $total > $returned,
        ];

        if (filter_var($request->query('include_meta', false), FILTER_VALIDATE_BOOL)) {
            return \response()->json([
                'items' => $items,
                'meta' => $meta,
            ]);
        }

        return \response()
            ->json($items)
            ->header('X-Template-Registry-Total', (string) $total)
            ->header('X-Template-Registry-Returned', (string) $returned)
            ->header('X-Template-Registry-Limit', (string) $limit)
            ->header('X-Template-Registry-Has-More', $meta['has_more'] ? '1' : '0');
    }
}
