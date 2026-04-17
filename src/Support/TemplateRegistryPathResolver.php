<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Support;

class TemplateRegistryPathResolver
{
    /** @param array<string,mixed> $config */
    public function migrationsPath(array $config): string
    {
        $path = trim((string) ($config['migrations']['path'] ?? 'core/custom/template-registry/migrations'));
        return $this->toAbsolutePath($path);
    }

    /** @param array<string,mixed> $config */
    public function outputPath(array $config): string
    {
        $output = trim((string) ($config['output'] ?? ''));
        if ($output !== '') {
            return $output;
        }

        $fallbacks = (array) ($config['output_fallbacks'] ?? []);
        if ($fallbacks === []) {
            $fallbacks = [
                'core/custom/packages/Main/generated/registry',
                'core/storage/app/template-registry/generated/registry',
            ];
        }

        foreach ($fallbacks as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $parent = $this->toAbsolutePath(dirname(trim($candidate)));
            if (is_dir($parent)) {
                return trim($candidate);
            }
        }

        return (string) reset($fallbacks);
    }

    public function projectRoot(): string
    {
        $basePath = function_exists('base_path') ? (string) \base_path() : (getcwd() ?: '');
        return rtrim(dirname($basePath), '/');
    }

    public function toAbsolutePath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->projectRoot() . '/' . ltrim($path, '/');
    }
}
