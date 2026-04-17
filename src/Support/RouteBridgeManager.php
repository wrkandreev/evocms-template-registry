<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Support;

class RouteBridgeManager
{
    private const START_MARKER = '// template-registry-routes:start';
    private const END_MARKER = '// template-registry-routes:end';

    public function install(): array
    {
        $path = $this->routesFilePath();
        $existing = is_file($path) ? (string) file_get_contents($path) : "<?php\n\n";

        if (str_contains($existing, self::START_MARKER) && str_contains($existing, self::END_MARKER)) {
            return [
                'path' => $path,
                'changed' => false,
            ];
        }

        $snippet = $this->snippet();
        $updated = rtrim($existing);
        if ($updated !== '' && !str_ends_with($updated, "\n")) {
            $updated .= "\n";
        }
        if ($updated !== '' && !str_ends_with($updated, "\n\n")) {
            $updated .= "\n";
        }

        $updated .= $snippet;

        $this->ensureParentDirectoryExists($path);
        file_put_contents($path, $updated);

        return [
            'path' => $path,
            'changed' => true,
        ];
    }

    public function uninstall(): array
    {
        $path = $this->routesFilePath();
        if (!is_file($path)) {
            return [
                'path' => $path,
                'changed' => false,
            ];
        }

        $existing = (string) file_get_contents($path);
        $pattern = '/' . preg_quote(self::START_MARKER, '/') . '.*?' . preg_quote(self::END_MARKER, '/') . '\R?/s';
        $updated = preg_replace($pattern, '', $existing, 1, $count);
        if ($count !== 1 || !is_string($updated)) {
            return [
                'path' => $path,
                'changed' => false,
            ];
        }

        $updated = preg_replace("/\n{3,}/", "\n\n", $updated);
        if ($updated === null) {
            $updated = $existing;
        }

        file_put_contents($path, rtrim($updated) . "\n");

        return [
            'path' => $path,
            'changed' => true,
        ];
    }

    public function isInstalled(): bool
    {
        $path = $this->routesFilePath();
        if (!is_file($path)) {
            return false;
        }

        $content = (string) file_get_contents($path);
        return str_contains($content, self::START_MARKER) && str_contains($content, self::END_MARKER);
    }

    private function snippet(): string
    {
        return <<<'PHP'
// template-registry-routes:start
if (is_file(__DIR__ . '/../vendor/wrkandreev/evocms-template-registry/routes.php')) {
    require_once __DIR__ . '/../vendor/wrkandreev/evocms-template-registry/routes.php';
}
// template-registry-routes:end

PHP;
    }

    private function routesFilePath(): string
    {
        $basePath = function_exists('base_path') ? (string) base_path() : (getcwd() ?: '');
        return rtrim($basePath, '/') . '/custom/routes.php';
    }

    private function ensureParentDirectoryExists(string $path): void
    {
        $parent = dirname($path);
        if (!is_dir($parent)) {
            mkdir($parent, 0775, true);
        }
    }
}
