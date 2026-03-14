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

    public function getAccessToken(string $default): string
    {
        $state = $this->readState();
        if (array_key_exists('access_token', $state)) {
            return trim((string) $state['access_token']);
        }

        return trim($default);
    }

    public function hasTokenOverride(): bool
    {
        $state = $this->readState();
        return array_key_exists('access_token', $state);
    }

    public function setEnabled(bool $enabled): void
    {
        $state = $this->readState();
        $state['enabled'] = $enabled;
        $this->writeState($state);
    }

    public function setAccessToken(string $token): void
    {
        $state = $this->readState();
        $state['access_token'] = trim($token);
        $this->writeState($state);
    }

    public function clearAccessTokenOverride(): void
    {
        $state = $this->readState();
        unset($state['access_token']);
        $this->writeState($state);
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

    /** @param array<string,mixed> $state */
    private function writeState(array $state): void
    {
        $path = $this->statePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($payload === false) {
            return;
        }

        file_put_contents($path, $payload . PHP_EOL, LOCK_EX);
    }
}
