<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Http\Controllers;

use Illuminate\Http\Request;
use RuntimeException;
use WrkAndreev\EvocmsTemplateRegistry\Services\TemplateRegistryWriteService;

class TemplateRegistryWriteApiController
{
    public function createTemplate(Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($request) {
            return $service->createTemplate((array) $request->all());
        }, 201);
    }

    public function createTv(Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($request) {
            return $service->createTv((array) $request->all());
        }, 201);
    }

    public function createResource(Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($request) {
            return $service->createResource((array) $request->all());
        }, 201);
    }

    public function attachTvToTemplate(int $templateId, int $tvId, Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($templateId, $tvId, $request) {
            return $service->attachTvToTemplate($templateId, $tvId, (array) $request->all());
        });
    }

    public function detachTvFromTemplate(int $templateId, int $tvId)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($templateId, $tvId) {
            return $service->detachTvFromTemplate($templateId, $tvId);
        });
    }

    public function setResourceTemplate(int $resourceId, Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($resourceId, $request) {
            return $service->setResourceTemplate($resourceId, (int) $request->input('template_id'));
        });
    }

    public function setResourceTvValue(int $resourceId, int $tvId, Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($resourceId, $tvId, $request) {
            return $service->setResourceTvValue($resourceId, $tvId, $request->input('value'));
        });
    }

    private function handle(callable $callback, int $successStatus = 200)
    {
        try {
            $service = new TemplateRegistryWriteService((array) \config('template-registry', []));
            $result = $callback($service);
        } catch (RuntimeException $e) {
            $status = $this->errorStatus($e->getMessage());
            return \response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], $status);
        }

        return \response()->json([
            'ok' => true,
        ] + $result, $successStatus);
    }

    private function errorStatus(string $message): int
    {
        $message = strtolower($message);

        foreach (['required', 'invalid', 'must', 'already exists', 'empty'] as $needle) {
            if (str_contains($message, $needle)) {
                return 422;
            }
        }

        foreach (['not found', 'missing'] as $needle) {
            if (str_contains($message, $needle)) {
                return 404;
            }
        }

        return 500;
    }
}
