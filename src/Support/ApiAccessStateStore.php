<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Support;

class ApiAccessStateStore
{
    public function isEnabled(bool $default): bool
    {
        $state = $this->readState();
        if (isset($state['enabled'])) {
            return (bool) $state['enabled'];
        }

        return $default;
    }

    public function setEnabled(bool $enabled): void
    {
        $path = $this->statePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = json_encode(['enabled' => $enabled], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($payload === false) {
            return;
        }

        file_put_contents($path, $payload . PHP_EOL, LOCK_EX);
    }

    /** @return array<string,mixed> */
    private function readState(): array
    {
        $path = $this->statePath();
        if (!is_file($path)) {
            return [];
        }

        $content = (string) @file_get_contents($path);
        if (trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function statePath(): string
    {
        if (\defined('MODX_CORE_PATH')) {
            $corePath = rtrim((string) \constant('MODX_CORE_PATH'), '/');
        } else {
            $basePath = getcwd() ?: '';
            $corePath = rtrim($basePath, '/');
            if (!str_ends_with($corePath, '/core')) {
                $corePath .= '/core';
            }
        }

        return $corePath . '/storage/app/template-registry-api-state.json';
    }
}
