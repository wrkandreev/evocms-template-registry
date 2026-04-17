<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use RuntimeException;
use WrkAndreev\EvocmsTemplateRegistry\Support\TemplateRegistryPathResolver;

class TemplateRegistryMigrationFileLoader
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** @return array<int,array{name:string,path:string,checksum:string,operations:array<int,array<string,mixed>>,description:string}> */
    public function loadAll(): array
    {
        $dir = (new TemplateRegistryPathResolver())->migrationsPath($this->config);
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob(rtrim($dir, '/') . '/*.php') ?: [];
        sort($files);

        $result = [];
        foreach ($files as $file) {
            $result[] = $this->loadFile($file);
        }

        return $result;
    }

    /** @return array{name:string,path:string,checksum:string,operations:array<int,array<string,mixed>>,description:string} */
    public function loadFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Migration file not found: ' . $path);
        }

        $payload = (static function (string $__file) {
            return require $__file;
        })($path);

        if (!is_array($payload)) {
            throw new RuntimeException('Migration file must return array: ' . $path);
        }

        $operations = $payload['operations'] ?? $payload;
        if (!is_array($operations)) {
            throw new RuntimeException('Migration operations must be array: ' . $path);
        }

        return [
            'name' => (string) ($payload['name'] ?? basename($path, '.php')),
            'path' => $path,
            'checksum' => hash_file('sha256', $path) ?: sha1_file($path) ?: '',
            'description' => (string) ($payload['description'] ?? ''),
            'operations' => array_values(array_filter($operations, 'is_array')),
        ];
    }
}
