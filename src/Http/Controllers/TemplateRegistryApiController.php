<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Http\Controllers;

use Illuminate\Http\Request;
use RuntimeException;
use WrkAndreev\EvocmsTemplateRegistry\Services\ResourceContextResolver;
use WrkAndreev\EvocmsTemplateRegistry\Services\TemplateRegistryGenerator;

class TemplateRegistryApiController
{
    public function index(Request $request)
    {
        try {
            $payload = $this->payload();
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        $templateId = $request->query('template_id');
        if ($templateId !== null && $templateId !== '') {
            $id = (int) $templateId;
            foreach ((array) ($payload['templates'] ?? []) as $template) {
                if ((int) ($template['id'] ?? 0) === $id) {
                    return \response()->json($template);
                }
            }

            return \response()->json([
                'message' => 'Template not found.',
            ], 404);
        }

        return \response()->json($payload);
    }

    public function templates()
    {
        try {
            return \response()->json((array) ($this->payload()['templates'] ?? []));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function templateById(int $id)
    {
        try {
            $templates = (array) ($this->payload()['templates'] ?? []);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        foreach ($templates as $template) {
            if ((int) ($template['id'] ?? 0) === $id) {
                return \response()->json($template);
            }
        }

        return \response()->json([
            'message' => 'Template not found.',
        ], 404);
    }

    public function tvCatalog()
    {
        try {
            return \response()->json((array) ($this->payload()['tv_catalog'] ?? []));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function stats()
    {
        try {
            return \response()->json((array) ($this->payload()['stats'] ?? []));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function resourceContext(Request $request)
    {
        try {
            $payload = $this->payload();
            $config = (array) \config('template-registry', []);
            $resolver = new ResourceContextResolver($config);
            $context = $resolver->resolve(
                $payload,
                $request->query('resource_id'),
                $request->query('url')
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        $status = (int) ($context['status'] ?? 200);
        unset($context['status']);

        return \response()->json($context, $status);
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        $config = (array) \config('template-registry', []);
        $generator = new TemplateRegistryGenerator($config);
        return $generator->buildPayload();
    }

    private function errorResponse(string $message)
    {
        return \response()->json([
            'message' => $message,
            'code' => 'registry_unavailable',
        ], 503);
    }
}
