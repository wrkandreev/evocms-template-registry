<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use WrkAndreev\EvocmsTemplateRegistry\Support\TemplateRegistryPathResolver;

class TemplateRegistryMigrationExecutor
{
    /** @var array<string,mixed> */
    private array $config;

    private TemplateRegistryMigrationStateRepository $stateRepository;
    private TemplateRegistryMigrationFileLoader $loader;
    private TemplateRegistryReferenceResolver $resolver;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->stateRepository = new TemplateRegistryMigrationStateRepository($config);
        $this->loader = new TemplateRegistryMigrationFileLoader($config);
        $this->resolver = new TemplateRegistryReferenceResolver($config);
    }

    /** @return array<int,array<string,mixed>> */
    public function status(): array
    {
        $applied = $this->stateRepository->allApplied();
        $items = [];
        foreach ($this->loader->loadAll() as $migration) {
            $name = (string) $migration['name'];
            $appliedRow = $applied[$name] ?? null;
            $items[] = [
                'name' => $name,
                'description' => (string) ($migration['description'] ?? ''),
                'path' => (string) ($migration['path'] ?? ''),
                'checksum' => (string) ($migration['checksum'] ?? ''),
                'applied' => $appliedRow !== null,
                'applied_at' => $appliedRow['applied_at'] ?? null,
                'checksum_matches' => $appliedRow === null ? null : (($appliedRow['checksum'] ?? '') === ($migration['checksum'] ?? '')),
            ];
        }

        return $items;
    }

    /** @return array<int,array{name:string,status:string}> */
    public function migrate(?string $only = null, bool $dryRun = false): array
    {
        $applied = $this->stateRepository->allApplied();
        $config = $this->config;
        $config['api']['regenerate_after_write'] = false;
        $writeService = new TemplateRegistryWriteService($config);

        $executed = [];
        foreach ($this->loader->loadAll() as $migration) {
            $name = (string) $migration['name'];
            if ($only !== null && $only !== '' && $name !== $only) {
                continue;
            }

            $appliedRow = $applied[$name] ?? null;
            if ($appliedRow !== null) {
                if (($appliedRow['checksum'] ?? '') !== ($migration['checksum'] ?? '')) {
                    throw new RuntimeException('Migration checksum changed after apply: ' . $name);
                }

                $executed[] = ['name' => $name, 'status' => 'skipped'];
                continue;
            }

            if ($dryRun) {
                $executed[] = ['name' => $name, 'status' => 'would_apply'];
                continue;
            }

            DB::transaction(function () use ($migration, $writeService, $name): void {
                foreach ((array) ($migration['operations'] ?? []) as $operation) {
                    if (!is_array($operation)) {
                        continue;
                    }
                    $this->executeOperation($writeService, $operation);
                }

                $this->stateRepository->markApplied($name, (string) ($migration['checksum'] ?? ''));
            });

            $executed[] = ['name' => $name, 'status' => 'applied'];
        }

        if (!$dryRun && array_filter($executed, static fn(array $row): bool => ($row['status'] ?? '') === 'applied') !== []) {
            $this->regenerateRegistry();
        }

        return $executed;
    }

    /** @param array<string,mixed> $operation */
    private function executeOperation(TemplateRegistryWriteService $writeService, array $operation): void
    {
        $op = (string) ($operation['op'] ?? '');
        if ($op === '') {
            throw new RuntimeException('Migration operation missing op.');
        }

        switch ($op) {
            case 'upsert_template':
                $this->upsertTemplate($writeService, $operation);
                return;
            case 'upsert_tv':
                $this->upsertTv($writeService, $operation);
                return;
            case 'attach_tv_to_template':
                $writeService->attachTvToTemplate(
                    $this->resolver->templateId((array) ($operation['template'] ?? [])),
                    $this->resolver->tvId((array) ($operation['tv'] ?? [])),
                    ['rank' => (int) ($operation['rank'] ?? 0)]
                );
                return;
            case 'detach_tv_from_template':
                $writeService->detachTvFromTemplate(
                    $this->resolver->templateId((array) ($operation['template'] ?? [])),
                    $this->resolver->tvId((array) ($operation['tv'] ?? []))
                );
                return;
            case 'upsert_resource':
                $this->upsertResource($writeService, $operation);
                return;
            case 'set_resource_published':
                $writeService->setResourcePublished(
                    $this->resolver->resourceId($operation['resource'] ?? []),
                    (bool) ($operation['published'] ?? false)
                );
                return;
            case 'set_resource_tv_value':
                $writeService->setResourceTvValue(
                    $this->resolver->resourceId($operation['resource'] ?? []),
                    $this->resolver->tvId($operation['tv'] ?? []),
                    $operation['value'] ?? null
                );
                return;
            case 'delete_resource':
                $writeService->deleteResource($this->resolver->resourceId($operation['resource'] ?? []));
                return;
            case 'restore_resource':
                $writeService->restoreResource($this->resolver->resourceId($operation['resource'] ?? [], true));
                return;
            case 'update_template':
                $writeService->updateTemplate(
                    $this->resolver->templateId($operation['template'] ?? []),
                    (array) ($operation['data'] ?? [])
                );
                return;
            case 'delete_template':
                $writeService->deleteTemplate($this->resolver->templateId($operation['template'] ?? []));
                return;
            case 'update_tv':
                $writeService->updateTv(
                    $this->resolver->tvId($operation['tv'] ?? []),
                    (array) ($operation['data'] ?? [])
                );
                return;
            case 'delete_tv':
                $writeService->deleteTv($this->resolver->tvId($operation['tv'] ?? []));
                return;
            case 'update_resource':
                $writeService->updateResource(
                    $this->resolver->resourceId($operation['resource'] ?? [], true),
                    $this->normalizeResourceData((array) ($operation['data'] ?? []))
                );
                return;
            case 'set_resource_template':
                $writeService->setResourceTemplate(
                    $this->resolver->resourceId($operation['resource'] ?? [], true),
                    $this->resolver->templateId($operation['template'] ?? [])
                );
                return;
        }

        throw new RuntimeException('Unsupported migration operation: ' . $op);
    }

    /** @param array<string,mixed> $operation */
    private function upsertTemplate(TemplateRegistryWriteService $writeService, array $operation): void
    {
        $match = (array) ($operation['match'] ?? []);
        $data = (array) ($operation['data'] ?? []);

        $id = $this->resolver->findTemplateId($match);
        if ($id !== null) {
            $writeService->updateTemplate($id, $data);
            return;
        }

        $writeService->createTemplate($data);
    }

    /** @param array<string,mixed> $operation */
    private function upsertTv(TemplateRegistryWriteService $writeService, array $operation): void
    {
        $match = (array) ($operation['match'] ?? []);
        $data = (array) ($operation['data'] ?? []);

        $id = $this->resolver->findTvId($match['name'] ?? $match);
        if ($id !== null) {
            $writeService->updateTv($id, $data);
            return;
        }

        $writeService->createTv($data);
    }

    /** @param array<string,mixed> $operation */
    private function upsertResource(TemplateRegistryWriteService $writeService, array $operation): void
    {
        $match = (array) ($operation['match'] ?? []);
        $data = $this->normalizeResourceData((array) ($operation['data'] ?? []));

        $id = $this->resolver->findResourceId($match, true);
        if ($id !== null) {
            $writeService->updateResource($id, $data);
            return;
        }

        $writeService->createResource($data);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function normalizeResourceData(array $data): array
    {
        if (isset($data['template']) && !isset($data['template_id'])) {
            $data['template_id'] = $this->resolver->templateId($data['template']);
            unset($data['template']);
        }

        if (isset($data['parent']) && !is_int($data['parent']) && !ctype_digit((string) $data['parent'])) {
            $data['parent'] = $this->resolver->resourceId($data['parent'], true);
        }

        if (isset($data['tv_values']) && is_array($data['tv_values'])) {
            $normalized = [];
            foreach ($data['tv_values'] as $tvRef => $value) {
                if (is_int($tvRef) || ctype_digit((string) $tvRef)) {
                    $normalized[(string) $tvRef] = $value;
                    continue;
                }

                $normalized[(string) $this->resolver->tvId(['name' => (string) $tvRef])] = $value;
            }
            $data['tv_values'] = $normalized;
        }

        return $data;
    }

    private function regenerateRegistry(): void
    {
        $generator = new TemplateRegistryGenerator($this->config);
        $payload = $generator->buildPayload();
        $format = strtolower((string) ($this->config['format'] ?? 'all'));
        if (!in_array($format, ['json', 'md', 'php', 'all'], true)) {
            $format = 'all';
        }

        $output = (new TemplateRegistryPathResolver())->outputPath($this->config);
        $generator->writePayload($payload, $output, $format);
    }
}
