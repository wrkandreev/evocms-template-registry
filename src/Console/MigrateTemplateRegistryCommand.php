<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Console;

use Illuminate\Console\Command;
use RuntimeException;
use WrkAndreev\EvocmsTemplateRegistry\Services\TemplateRegistryMigrationExecutor;

class MigrateTemplateRegistryCommand extends Command
{
    protected $signature = 'template-registry:migrate {name? : One migration name to apply}';

    protected $description = 'Apply template registry content migrations';

    public function handle(): int
    {
        try {
            $rows = (new TemplateRegistryMigrationExecutor((array) config('template-registry', [])))
                ->migrate((string) ($this->argument('name') ?? ''));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($rows === []) {
            $this->info('No migrations found.');
            return self::SUCCESS;
        }

        $this->table(['Migration', 'Status'], array_map(static function (array $row): array {
            return [(string) ($row['name'] ?? ''), (string) ($row['status'] ?? '')];
        }, $rows));

        return self::SUCCESS;
    }
}
