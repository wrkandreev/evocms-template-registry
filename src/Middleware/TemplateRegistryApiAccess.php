<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Middleware;

use Closure;
use Illuminate\Http\Request;
use WrkAndreev\EvocmsTemplateRegistry\Support\ApiAccessStateStore;

class TemplateRegistryApiAccess
{
    public function handle(Request $request, Closure $next)
    {
        $config = (array) \config('template-registry', []);
        $api = (array) ($config['api'] ?? []);

        $stateStore = new ApiAccessStateStore();
        $enabledByDefault = (bool) ($api['enabled'] ?? true);
        if (!$stateStore->isEnabled($enabledByDefault)) {
            return \response()->json([
                'message' => 'Template registry API is disabled.',
            ], 403);
        }

        $accessToken = trim((string) ($api['access_token'] ?? ''));
        $writeToken = trim((string) ($api['write_access_token'] ?? ''));
        $requestToken = trim((string) $request->header('X-Template-Registry-Token', ''));
        $requestWriteToken = trim((string) $request->header('X-Template-Registry-Write-Token', ''));

        if ($accessToken !== '' && $requestToken !== '' && hash_equals($accessToken, $requestToken)) {
            return $next($request);
        }

        // A valid write token should also pass base API access checks.
        if ($writeToken !== '' && $requestWriteToken !== '' && hash_equals($writeToken, $requestWriteToken)) {
            return $next($request);
        }

        $requireManager = (bool) ($api['require_manager'] ?? true);
        if ($requireManager && \ManagerTheme::hasManagerAccess() === false) {
            return \response()->json([
                'message' => 'No manager access.',
            ], 403);
        }

        return $next($request);
    }
}
