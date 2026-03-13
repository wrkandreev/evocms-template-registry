<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Middleware;

use Closure;
use Illuminate\Http\Request;

class TemplateRegistryManagerAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (\ManagerTheme::hasManagerAccess() === false) {
            return \response('No manager access', 403);
        }

        return $next($request);
    }
}
