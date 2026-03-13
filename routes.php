<?php

use Illuminate\Support\Facades\Route;
use WrkAndreev\EvocmsTemplateRegistry\Http\Controllers\TemplateRegistryAccessModuleController;
use WrkAndreev\EvocmsTemplateRegistry\Http\Controllers\TemplateRegistryApiController;
use WrkAndreev\EvocmsTemplateRegistry\Middleware\TemplateRegistryApiAccess;
use WrkAndreev\EvocmsTemplateRegistry\Middleware\TemplateRegistryManagerAccess;

$apiConfig = (array) config('template-registry.api', []);
$apiPrefix = trim((string) ($apiConfig['prefix'] ?? 'api/template-registry'), '/');
$apiMiddleware = $apiConfig['middleware'] ?? ['web'];
if (!is_array($apiMiddleware)) {
    $apiMiddleware = [$apiMiddleware];
}
$apiMiddleware[] = TemplateRegistryApiAccess::class;

Route::prefix($apiPrefix)
    ->middleware($apiMiddleware)
    ->group(function (): void {
        Route::get('/', [TemplateRegistryApiController::class, 'index']);
        Route::get('/templates', [TemplateRegistryApiController::class, 'templates']);
        Route::get('/templates/{id}', [TemplateRegistryApiController::class, 'templateById'])->where('id', '[0-9]+');
        Route::get('/tvs', [TemplateRegistryApiController::class, 'tvCatalog']);
        Route::get('/stats', [TemplateRegistryApiController::class, 'stats']);
        Route::get('/resource-context', [TemplateRegistryApiController::class, 'resourceContext']);
    });

$adminPrefix = trim((string) ($apiConfig['admin_prefix'] ?? 'manager/template-registry'), '/');

Route::prefix($adminPrefix)
    ->middleware(['web', TemplateRegistryManagerAccess::class])
    ->group(function (): void {
        Route::get('/access', [TemplateRegistryAccessModuleController::class, 'index']);
        Route::get('/access/toggle', [TemplateRegistryAccessModuleController::class, 'toggle']);
    });
