<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Http\Controllers;

use WrkAndreev\EvocmsTemplateRegistry\Services\TemplateRegistryGenerator;

class TemplateRegistryApiController
{
    public function index($request)
    {
        $payload = $this->payload();

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
        return \response()->json((array) ($this->payload()['templates'] ?? []));
    }

    public function templateById(int $id)
    {
        foreach ((array) ($this->payload()['templates'] ?? []) as $template) {
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
        return \response()->json((array) ($this->payload()['tv_catalog'] ?? []));
    }

    public function stats()
    {
        return \response()->json((array) ($this->payload()['stats'] ?? []));
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        $config = (array) \config('template-registry', []);
        $generator = new TemplateRegistryGenerator($config);
        return $generator->buildPayload();
    }
}
