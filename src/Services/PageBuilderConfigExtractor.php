<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

class PageBuilderConfigExtractor
{
    /** @var array<string,mixed> */
    private array $config;

    private string $projectRoot;

    /** @param array<string,mixed> $config */
    public function __construct(array $config, string $projectRoot)
    {
        $this->config = $config;
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    /** @return array<string,mixed> */
    public function extract(): array
    {
        $configDir = $this->absolutePath((string) $this->cfg('pagebuilder.config_path', 'assets/plugins/pagebuilder/config'));
        $configDirExists = is_dir($configDir);

        $result = [
            'exists' => $configDirExists,
            'configs' => [],
            'stats' => [
                'configs_total' => 0,
                'configs_valid' => 0,
                'configs_invalid' => 0,
                'sample_files_total' => 0,
                'containers_total' => 0,
                'groups_files_total' => 0,
            ],
        ];

        if (!$configDirExists) {
            return $result;
        }

        $files = array_merge(
            glob($configDir . '/*.php') ?: [],
            glob($configDir . '/*.php.sample') ?: []
        );
        $files = array_values(array_unique($files));
        sort($files);

        $result['stats']['configs_total'] = count($files);

        foreach ($files as $filePath) {
            $item = $this->extractConfigFromFile($filePath);
            $result['configs'][] = $item;

            if (!empty($item['is_sample'])) {
                $result['stats']['sample_files_total']++;
            }
            if (($item['kind'] ?? '') === 'container') {
                $result['stats']['containers_total']++;
            }
            if (($item['kind'] ?? '') === 'groups') {
                $result['stats']['groups_files_total']++;
            }
            if (!empty($item['valid'])) {
                $result['stats']['configs_valid']++;
            } else {
                $result['stats']['configs_invalid']++;
            }
        }

        return $result;
    }

    /** @return array<string,mixed>|null */
    public function findByName(string $name): ?array
    {
        $normalizedName = $this->normalizeName($name);
        if ($normalizedName === '') {
            return null;
        }

        foreach ((array) ($this->extract()['configs'] ?? []) as $config) {
            if (!is_array($config)) {
                continue;
            }

            if ($this->normalizeName((string) ($config['name'] ?? '')) === $normalizedName) {
                return $config;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function extractConfigFromFile(string $filePath): array
    {
        $fileName = basename($filePath);
        $name = $this->displayNameFromFile($fileName);
        $kind = $this->detectKind($name);

        try {
            $raw = $this->loadPhpArray($filePath);
        } catch (\Throwable $e) {
            return [
                'name' => $name,
                'kind' => $kind,
                'title' => $name,
                'source_file' => $this->toProjectRelativePath($filePath),
                'is_sample' => $this->isSampleFile($fileName),
                'valid' => false,
                'error' => 'Failed to load PageBuilder config: ' . $e->getMessage(),
                'config' => [],
            ];
        }

        if (!is_array($raw)) {
            return [
                'name' => $name,
                'kind' => $kind,
                'title' => $name,
                'source_file' => $this->toProjectRelativePath($filePath),
                'is_sample' => $this->isSampleFile($fileName),
                'valid' => false,
                'error' => 'PageBuilder config must return array.',
                'config' => [],
            ];
        }

        return [
            'name' => $name,
            'kind' => $kind,
            'title' => $this->resolveTitle($name, $raw),
            'source_file' => $this->toProjectRelativePath($filePath),
            'is_sample' => $this->isSampleFile($fileName),
            'valid' => true,
            'error' => null,
            'config' => $raw,
        ];
    }

    private function displayNameFromFile(string $fileName): string
    {
        $name = preg_replace('/\.php\.sample$/i', '', $fileName) ?: $fileName;
        $name = preg_replace('/\.php$/i', '', $name) ?: $name;
        return $name;
    }

    private function detectKind(string $name): string
    {
        if ($name === 'groups') {
            return 'groups';
        }

        if (str_starts_with($name, 'container.')) {
            return 'container';
        }

        return 'block';
    }

    private function resolveTitle(string $name, array $raw): string
    {
        $title = trim((string) ($raw['title'] ?? $raw['caption'] ?? $raw['name'] ?? ''));
        return $title !== '' ? $title : $name;
    }

    private function isSampleFile(string $fileName): bool
    {
        return str_ends_with(strtolower($fileName), '.php.sample');
    }

    private function normalizeName(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/\.php\.sample$/i', '', $normalized) ?: $normalized;
        $normalized = preg_replace('/\.php$/i', '', $normalized) ?: $normalized;
        return $normalized;
    }

    /** @return mixed */
    private function loadPhpArray(string $filePath)
    {
        return (static function (string $__file) {
            return include $__file;
        })($filePath);
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            return $this->projectRoot;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->projectRoot . '/' . ltrim($path, '/');
    }

    private function toProjectRelativePath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', $this->projectRoot);
        $rootWithSlash = rtrim($root, '/') . '/';

        if (str_starts_with($normalizedPath, $rootWithSlash)) {
            return ltrim(substr($normalizedPath, strlen($rootWithSlash)), '/');
        }

        return $path;
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
