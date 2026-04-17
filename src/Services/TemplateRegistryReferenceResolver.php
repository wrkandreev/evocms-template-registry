<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TemplateRegistryReferenceResolver
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** @param array<string,mixed>|int|string $reference */
    public function templateId(array|int|string $reference): int
    {
        $id = $this->findTemplateId($reference);
        if ($id !== null) {
            return $id;
        }

        throw new RuntimeException('Template reference not found.');
    }

    /** @param array<string,mixed>|int|string $reference */
    public function findTemplateId(array|int|string $reference): ?int
    {
        if (is_int($reference) || ctype_digit((string) $reference)) {
            return (int) $reference;
        }

        $table = $this->requireTable('templates_table', 'site_templates');
        $ref = (array) $reference;
        if (!empty($ref['id'])) {
            return (int) $ref['id'];
        }
        if (!empty($ref['alias'])) {
            $id = (int) DB::table($table)->where('templatealias', (string) $ref['alias'])->value('id');
            if ($id > 0) {
                return $id;
            }
        }
        if (!empty($ref['name'])) {
            $id = (int) DB::table($table)->where('templatename', (string) $ref['name'])->value('id');
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    /** @param array<string,mixed>|int|string $reference */
    public function tvId(array|int|string $reference): int
    {
        $id = $this->findTvId($reference);
        if ($id !== null) {
            return $id;
        }

        throw new RuntimeException('TV reference not found.');
    }

    /** @param array<string,mixed>|int|string $reference */
    public function findTvId(array|int|string $reference): ?int
    {
        if (is_int($reference) || ctype_digit((string) $reference)) {
            return (int) $reference;
        }

        $table = $this->requireTable('tvs_table', 'site_tmplvars');
        $ref = (array) $reference;
        if (!empty($ref['id'])) {
            return (int) $ref['id'];
        }
        $name = (string) ($ref['name'] ?? $reference);
        $id = (int) DB::table($table)->where('name', $name)->value('id');
        if ($id > 0) {
            return $id;
        }

        return null;
    }

    /** @param array<string,mixed>|int|string $reference */
    public function resourceId(array|int|string $reference, bool $includeDeleted = true): int
    {
        $id = $this->findResourceId($reference, $includeDeleted);
        if ($id !== null) {
            return $id;
        }

        throw new RuntimeException('Resource reference not found.');
    }

    /** @param array<string,mixed>|int|string $reference */
    public function findResourceId(array|int|string $reference, bool $includeDeleted = true): ?int
    {
        if (is_int($reference) || ctype_digit((string) $reference)) {
            return (int) $reference;
        }

        $ref = (array) $reference;
        if (!empty($ref['id'])) {
            return (int) $ref['id'];
        }

        $config = (array) $this->config;
        $payload = (new TemplateRegistryGenerator($config))->buildPayload();
        $resolver = new ResourceContextResolver($config);

        if (!empty($ref['path'])) {
            $item = $resolver->resolveResourceId(null, (string) $ref['path']);
            $id = (int) ($item['resource_id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        $alias = (string) ($ref['alias'] ?? '');
        if ($alias !== '') {
            $parentId = 0;
            if (array_key_exists('parent', $ref)) {
                $parentId = $this->resourceId($ref['parent'], $includeDeleted);
            }

            foreach ($resolver->listResources($payload, 1000000, $includeDeleted) as $resource) {
                if ((string) ($resource['alias'] ?? '') === $alias && (int) ($resource['parent'] ?? 0) === $parentId) {
                    return (int) ($resource['id'] ?? 0);
                }
            }
        }

        return null;
    }

    private function requireTable(string $configKey, string $default): string
    {
        $base = (string) ($this->config[$configKey] ?? $default);
        if ($base === '') {
            throw new RuntimeException('Required table config missing for ' . $configKey . '.');
        }

        if (Schema::hasTable($base)) {
            return $base;
        }

        $defaultConnection = (string) \config('database.default');
        $prefix = (string) \config("database.connections.{$defaultConnection}.prefix", '');

        if ($prefix !== '') {
            $prefixed = $prefix . $base;
            if (Schema::hasTable($prefixed)) {
                return $prefixed;
            }
        }

        return $base;
    }
}
