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
