<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Http\Controllers;

use Illuminate\Http\Request;
use RuntimeException;
use WrkAndreev\EvocmsTemplateRegistry\Services\BLangDefaultParamsService;
use WrkAndreev\EvocmsTemplateRegistry\Services\BLangFieldService;
use WrkAndreev\EvocmsTemplateRegistry\Services\BLangHealthService;
use WrkAndreev\EvocmsTemplateRegistry\Services\BLangLexiconService;
use WrkAndreev\EvocmsTemplateRegistry\Services\BLangSettingsService;
use WrkAndreev\EvocmsTemplateRegistry\Services\ClientSettingsWriteService;
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

    public function updateClientSettings(Request $request)
    {
        return $this->handleClientSettings(function (ClientSettingsWriteService $service) use ($request) {
            return $service->updateValues((array) $request->all());
        });
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

    public function setResourceBLangFields(int $resourceId, Request $request)
    {
        return $this->handle(function (TemplateRegistryWriteService $service) use ($resourceId, $request) {
            return $service->setResourceBLangFieldValues($resourceId, (array) $request->all());
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

    public function createBLangLexiconEntry(Request $request)
    {
        return $this->handleBLang(function (BLangLexiconService $service) use ($request) {
            return $service->createEntry((array) $request->all());
        }, 201);
    }

    public function createBLangField(Request $request)
    {
        return $this->handleBLangField(function (BLangFieldService $service) use ($request) {
            return $service->createField((array) $request->all());
        }, 201);
    }

    public function updateBLangLexiconEntry(int $entryId, Request $request)
    {
        return $this->handleBLang(function (BLangLexiconService $service) use ($entryId, $request) {
            return $service->updateEntry($entryId, (array) $request->all());
        });
    }

    public function updateBLangField(int $fieldId, Request $request)
    {
        return $this->handleBLangField(function (BLangFieldService $service) use ($fieldId, $request) {
            return $service->updateField($fieldId, (array) $request->all());
        });
    }

    public function deleteBLangLexiconEntry(int $entryId)
    {
        return $this->handleBLang(function (BLangLexiconService $service) use ($entryId) {
            return $service->deleteEntry($entryId);
        });
    }

    public function deleteBLangField(int $fieldId)
    {
        return $this->handleBLangField(function (BLangFieldService $service) use ($fieldId) {
            return $service->deleteField($fieldId);
        });
    }

    public function createBLangDefaultParams(Request $request)
    {
        return $this->handleBLangDefaults(function (BLangDefaultParamsService $service) use ($request) {
            return $service->createDefaultParams((bool) $request->input('attach_all_templates', false));
        }, 201);
    }

    public function updateBLangSettings(Request $request)
    {
        return $this->handleBLangSettings(function (BLangSettingsService $service) use ($request) {
            return $service->updateSettings((array) $request->all());
        });
    }

    public function removeBLangLanguage(string $language, Request $request)
    {
        return $this->handleBLangSettings(function (BLangSettingsService $service) use ($language, $request) {
            return $service->removeLanguage($language, $request->input('new_default'));
        });
    }

    public function fixBLangTemplateLinks()
    {
        return $this->handleBLangHealth(function (BLangHealthService $service) {
            return $service->fixTemplateLinks();
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

    private function handleBLang(callable $callback, int $successStatus = 200)
    {
        try {
            $service = new BLangLexiconService((array) \config('template-registry', []));
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

    private function handleBLangDefaults(callable $callback, int $successStatus = 200)
    {
        try {
            $service = new BLangDefaultParamsService((array) \config('template-registry', []));
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

    private function handleBLangField(callable $callback, int $successStatus = 200)
    {
        try {
            $service = new BLangFieldService((array) \config('template-registry', []));
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

    private function handleBLangSettings(callable $callback, int $successStatus = 200)
    {
        try {
            $service = new BLangSettingsService((array) \config('template-registry', []));
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

    private function handleBLangHealth(callable $callback, int $successStatus = 200)
    {
        try {
            $service = new BLangHealthService((array) \config('template-registry', []));
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

    private function handleClientSettings(callable $callback, int $successStatus = 200)
    {
        try {
            $service = new ClientSettingsWriteService((array) \config('template-registry', []));
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
