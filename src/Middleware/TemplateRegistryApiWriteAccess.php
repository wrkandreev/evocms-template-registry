<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Middleware;

use Closure;
use Illuminate\Http\Request;
use WrkAndreev\EvocmsTemplateRegistry\Support\ApiAccessStateStore;

class TemplateRegistryApiWriteAccess
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

        if (empty($api['write_enabled'])) {
            return \response()->json([
                'message' => 'Template registry write API is disabled.',
            ], 403);
        }

        $writeToken = trim((string) ($api['write_access_token'] ?? ''));
        $requestToken = trim((string) $request->header('X-Template-Registry-Write-Token', ''));
        if ($writeToken !== '' && $requestToken !== '' && hash_equals($writeToken, $requestToken)) {
            return $next($request);
        }

        if (\ManagerTheme::hasManagerAccess() === true) {
            return $next($request);
        }

        return \response()->json([
            'message' => 'No write access.',
        ], 403);
    }
}
