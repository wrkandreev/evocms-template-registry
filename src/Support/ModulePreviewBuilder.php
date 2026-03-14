<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Support;

use RuntimeException;
use WrkAndreev\EvocmsTemplateRegistry\Services\ResourceContextResolver;
use WrkAndreev\EvocmsTemplateRegistry\Services\TemplateRegistryGenerator;

class ModulePreviewBuilder
{
    /**
     * @param array<string,mixed> $config
     * @return array{preview:array<string,mixed>|null,error:string|null}
     */
    public function build(array $config): array
    {
        try {
            $payload = (new TemplateRegistryGenerator($config))->buildPayload();
        } catch (RuntimeException $e) {
            return [
                'preview' => null,
                'error' => $e->getMessage(),
            ];
        }

        $resources = $this->buildResourcesPreview($config, $payload);

        return [
            'preview' => [
                'generated_at' => (string) ($payload['generated_at'] ?? ''),
                'stats' => (array) ($payload['stats'] ?? []),
                'templates' => array_slice($this->mapTemplates((array) ($payload['templates'] ?? [])), 0, 100),
                'tv_catalog' => array_slice($this->mapTvCatalog((array) ($payload['tv_catalog'] ?? [])), 0, 200),
                'resources' => $resources,
                'templates_total' => count((array) ($payload['templates'] ?? [])),
                'tv_total' => count((array) ($payload['tv_catalog'] ?? [])),
                'resources_total' => count($resources),
                'templates_truncated' => count((array) ($payload['templates'] ?? [])) > 100,
                'tv_truncated' => count((array) ($payload['tv_catalog'] ?? [])) > 200,
                'resources_truncated' => count($resources) >= 100,
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<int,mixed> $templates
     * @return array<int,array<string,mixed>>
     */
    private function mapTemplates(array $templates): array
    {
        $result = [];
        foreach ($templates as $template) {
            if (!is_array($template)) {
                continue;
            }

            $result[] = [
                'id' => (int) ($template['id'] ?? 0),
                'name' => (string) ($template['name'] ?? ''),
                'alias' => (string) ($template['alias'] ?? ''),
                'tv_count' => count((array) ($template['tv_refs'] ?? [])),
                'controller_class' => (string) (($template['controller']['class'] ?? '')),
                'controller_exists' => (bool) (($template['controller']['exists'] ?? false)),
                'view_path' => (string) (($template['view']['path'] ?? '')),
                'view_exists' => (bool) (($template['view']['exists'] ?? false)),
            ];
        }

        return $result;
    }

    /**
     * @param array<int,mixed> $tvCatalog
     * @return array<int,array<string,mixed>>
     */
    private function mapTvCatalog(array $tvCatalog): array
    {
        $result = [];
        foreach ($tvCatalog as $tv) {
            if (!is_array($tv)) {
                continue;
            }

            $result[] = [
                'id' => (int) ($tv['id'] ?? 0),
                'name' => (string) ($tv['name'] ?? ''),
                'caption' => (string) ($tv['caption'] ?? ''),
                'type' => (string) ($tv['type'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    private function buildResourcesPreview(array $config, array $payload): array
    {
        try {
            $resources = (new ResourceContextResolver($config))->listResources($payload, 100);
        } catch (RuntimeException) {
            return [];
        }

        return array_map(static function (array $resource): array {
            return [
                'id' => (int) ($resource['id'] ?? 0),
                'pagetitle' => (string) ($resource['pagetitle'] ?? ''),
                'alias' => (string) ($resource['alias'] ?? ''),
                'uri' => (string) ($resource['uri'] ?? ''),
                'template_id' => (int) ($resource['template_id'] ?? 0),
                'template_name' => (string) ($resource['template_name'] ?? ''),
                'published' => $resource['published'] ?? null,
                'deleted' => $resource['deleted'] ?? null,
            ];
        }, $resources);
    }
}
