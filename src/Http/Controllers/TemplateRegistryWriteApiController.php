<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Http\Controllers;

use Illuminate\Http\Request;
use RuntimeException;
use WrkAndreev\EvocmsTemplateRegistry\Support\TemplateRegistryErrorMapper;
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

    public function setResourcePublished(int $resourceId, Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($resourceId, $request) {
            return $service->setResourcePublished($resourceId, (bool) $request->input('published'));
        });
    }

    public function updateTemplate(int $templateId, Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($templateId, $request) {
            return $service->updateTemplate($templateId, (array) $request->all());
        });
    }

    public function deleteTemplate(int $templateId)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($templateId) {
            return $service->deleteTemplate($templateId);
        });
    }

    public function updateTv(int $tvId, Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($tvId, $request) {
            return $service->updateTv($tvId, (array) $request->all());
        });
    }

    public function deleteTv(int $tvId)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($tvId) {
            return $service->deleteTv($tvId);
        });
    }

    public function updateResource(int $resourceId, Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($resourceId, $request) {
            return $service->updateResource($resourceId, (array) $request->all());
        });
    }

    public function deleteResource(int $resourceId)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($resourceId) {
            return $service->deleteResource($resourceId);
        });
    }

    public function restoreResource(int $resourceId)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($resourceId) {
            return $service->restoreResource($resourceId);
        });
    }

    private function handle(callable $callback, int $successStatus = 200)
    {
        try {
            $service = new TemplateRegistryWriteService((array) \config('template-registry', []));
            $result = $callback($service);
        } catch (RuntimeException $e) {
            $mapped = (new TemplateRegistryErrorMapper())->map($e->getMessage());
            return \response()->json([
                'ok' => false,
                'code' => $mapped['code'],
                'message' => $e->getMessage(),
            ], $mapped['status']);
        }

        return \response()->json([
            'ok' => true,
        ] + $result, $successStatus);
    }
}
