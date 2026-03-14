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
                'client_settings' => $this->buildClientSettingsPreview((array) ($payload['client_settings'] ?? [])),
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
                'type' => (string) ($resource['type'] ?? ''),
                'content_type' => (string) ($resource['content_type'] ?? ''),
                'pagetitle' => (string) ($resource['pagetitle'] ?? ''),
                'longtitle' => (string) ($resource['longtitle'] ?? ''),
                'description' => (string) ($resource['description'] ?? ''),
                'alias' => (string) ($resource['alias'] ?? ''),
                'uri' => (string) ($resource['uri'] ?? ''),
                'introtext' => (string) ($resource['introtext'] ?? ''),
                'template_id' => (int) ($resource['template_id'] ?? 0),
                'template_name' => (string) ($resource['template_name'] ?? ''),
                'menuindex' => $resource['menuindex'] ?? null,
                'parent' => $resource['parent'] ?? null,
                'isfolder' => $resource['isfolder'] ?? null,
                'published' => $resource['published'] ?? null,
                'pub_date' => $resource['pub_date'] ?? null,
                'unpub_date' => $resource['unpub_date'] ?? null,
                'deleted' => $resource['deleted'] ?? null,
                'richtext' => $resource['richtext'] ?? null,
                'searchable' => $resource['searchable'] ?? null,
                'cacheable' => $resource['cacheable'] ?? null,
                'createdon' => $resource['createdon'] ?? null,
                'editedon' => $resource['editedon'] ?? null,
                'publishedon' => $resource['publishedon'] ?? null,
                'menutitle' => (string) ($resource['menutitle'] ?? ''),
                'hide_from_tree' => $resource['hide_from_tree'] ?? null,
                'privateweb' => $resource['privateweb'] ?? null,
                'privatemgr' => $resource['privatemgr'] ?? null,
                'content_dispo' => $resource['content_dispo'] ?? null,
                'hidemenu' => $resource['hidemenu'] ?? null,
                'alias_visible' => $resource['alias_visible'] ?? null,
            ];
        }, $resources);
    }

    /** @param array<string,mixed> $clientSettings @return array<string,mixed> */
    private function buildClientSettingsPreview(array $clientSettings): array
    {
        $tabs = [];
        foreach ((array) ($clientSettings['tabs'] ?? []) as $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $tabs[] = [
                'id' => (string) ($tab['id'] ?? ''),
                'name' => (string) ($tab['name'] ?? ''),
                'source_file' => (string) ($tab['source_file'] ?? ''),
                'valid' => (bool) ($tab['valid'] ?? false),
                'error' => (string) ($tab['error'] ?? ''),
                'fields_count' => count((array) ($tab['fields'] ?? [])),
            ];
        }

        $fields = [];
        foreach ((array) ($clientSettings['fields_catalog'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $selector = is_array($field['selector'] ?? null) ? $field['selector'] : null;
            $fields[] = [
                'tab_id' => (string) ($field['tab_id'] ?? ''),
                'name' => (string) ($field['name'] ?? ''),
                'caption' => (string) ($field['caption'] ?? ''),
                'type' => (string) ($field['type'] ?? ''),
                'required' => (bool) ($field['required'] ?? false),
                'selector_controller' => is_array($selector) ? (string) ($selector['controller'] ?? '') : '',
                'selector_exists' => is_array($selector) ? (bool) ($selector['controller_exists'] ?? false) : false,
            ];
        }

        return [
            'exists' => (bool) ($clientSettings['exists'] ?? false),
            'stats' => (array) ($clientSettings['stats'] ?? []),
            'tabs' => array_slice($tabs, 0, 50),
            'fields' => array_slice($fields, 0, 200),
            'tabs_total' => count($tabs),
            'fields_total' => count($fields),
            'tabs_truncated' => count($tabs) > 50,
            'fields_truncated' => count($fields) > 200,
        ];
    }
}
