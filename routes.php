<?php

use Illuminate\Support\Facades\Route;
use WrkAndreev\EvocmsTemplateRegistry\Http\Controllers\TemplateRegistryAccessModuleController;
use WrkAndreev\EvocmsTemplateRegistry\Http\Controllers\TemplateRegistryApiController;
use WrkAndreev\EvocmsTemplateRegistry\Http\Controllers\TemplateRegistryWriteApiController;
use WrkAndreev\EvocmsTemplateRegistry\Middleware\TemplateRegistryApiAccess;
use WrkAndreev\EvocmsTemplateRegistry\Middleware\TemplateRegistryApiWriteAccess;
use WrkAndreev\EvocmsTemplateRegistry\Middleware\TemplateRegistryManagerAccess;

$apiConfig = (array) config('template-registry.api', []);
$apiPrefix = trim((string) ($apiConfig['prefix'] ?? 'api/template-registry'), '/');
$apiMiddleware = $apiConfig['middleware'] ?? ['global'];
if (!is_array($apiMiddleware)) {
    $apiMiddleware = [$apiMiddleware];
}

$globalMiddleware = (array) config('app.middleware.global', []);
$mgrMiddleware = (array) config('app.middleware.mgr', []);

$apiMiddleware = array_values(array_filter(array_map(static function ($middleware) {
    if (!is_string($middleware) || trim($middleware) === '') {
        return null;
    }

    if ($middleware === 'web') {
        return 'global';
    }

    return $middleware;
}, $apiMiddleware)));

$expandedApiMiddleware = [];
foreach ($apiMiddleware as $middleware) {
    if ($middleware === 'global') {
        $expandedApiMiddleware = array_merge($expandedApiMiddleware, $globalMiddleware);
        continue;
    }

    if ($middleware === 'mgr') {
        $expandedApiMiddleware = array_merge($expandedApiMiddleware, $mgrMiddleware);
        continue;
    }

    $expandedApiMiddleware[] = $middleware;
}

$apiMiddleware = array_values(array_unique(array_merge($expandedApiMiddleware, [TemplateRegistryApiAccess::class])));

Route::prefix($apiPrefix)
    ->middleware($apiMiddleware)
    ->group(function (): void {
        Route::get('/', [TemplateRegistryApiController::class, 'index']);
        Route::get('/templates', [TemplateRegistryApiController::class, 'templates']);
        Route::get('/templates/{id}', [TemplateRegistryApiController::class, 'templateById'])->where('id', '[0-9]+');
        Route::get('/tvs', [TemplateRegistryApiController::class, 'tvCatalog']);
        Route::get('/resources', [TemplateRegistryApiController::class, 'resources']);
        Route::get('/stats', [TemplateRegistryApiController::class, 'stats']);
        Route::get('/resource-resolve', [TemplateRegistryApiController::class, 'resourceResolve']);
        Route::get('/resource-context', [TemplateRegistryApiController::class, 'resourceContext']);
        Route::get('/pagebuilder-configs', [TemplateRegistryApiController::class, 'pageBuilderConfigs']);
        Route::get('/pagebuilder-configs/{name}', [TemplateRegistryApiController::class, 'pageBuilderConfigByName']);
    });

$writeMiddleware = $apiMiddleware;
$writeMiddleware[] = TemplateRegistryApiWriteAccess::class;

Route::prefix($apiPrefix)
    ->middleware($writeMiddleware)
    ->group(function (): void {
        Route::post('/templates', [TemplateRegistryWriteApiController::class, 'createTemplate']);
        Route::post('/tvs', [TemplateRegistryWriteApiController::class, 'createTv']);
        Route::post('/resources', [TemplateRegistryWriteApiController::class, 'createResource']);
        Route::put('/templates/{templateId}/tvs/{tvId}', [TemplateRegistryWriteApiController::class, 'attachTvToTemplate'])->where(['templateId' => '[0-9]+', 'tvId' => '[0-9]+']);
        Route::delete('/templates/{templateId}/tvs/{tvId}', [TemplateRegistryWriteApiController::class, 'detachTvFromTemplate'])->where(['templateId' => '[0-9]+', 'tvId' => '[0-9]+']);
        Route::put('/resources/{resourceId}/template', [TemplateRegistryWriteApiController::class, 'setResourceTemplate'])->where('resourceId', '[0-9]+');
        Route::put('/resources/{resourceId}/tv-values/{tvId}', [TemplateRegistryWriteApiController::class, 'setResourceTvValue'])->where(['resourceId' => '[0-9]+', 'tvId' => '[0-9]+']);
    });

$adminPrefix = trim((string) ($apiConfig['admin_prefix'] ?? 'template-registry-admin'), '/');

Route::prefix($adminPrefix)
    ->middleware(array_values(array_unique(array_merge($mgrMiddleware, [TemplateRegistryManagerAccess::class]))))
    ->group(function (): void {
        Route::get('/access', [TemplateRegistryAccessModuleController::class, 'index']);
        Route::post('/access/settings', [TemplateRegistryAccessModuleController::class, 'saveSettings']);
    });
