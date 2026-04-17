<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Console;

use Illuminate\Console\Command;
use WrkAndreev\EvocmsTemplateRegistry\Services\TemplateRegistryMigrationExecutor;

class TemplateRegistryMigrationStatusCommand extends Command
{
    protected $signature = 'template-registry:migrate:status';

    protected $description = 'Show template registry content migration status';

    public function handle(): int
    {
        $rows = (new TemplateRegistryMigrationExecutor((array) config('template-registry', [])))->status();
        if ($rows === []) {
            $this->info('No migrations found.');
            return self::SUCCESS;
        }

        $this->table(
            ['Migration', 'Applied', 'Applied At', 'Checksum OK'],
            array_map(static function (array $row): array {
                return [
                    (string) ($row['name'] ?? ''),
                    !empty($row['applied']) ? 'yes' : 'no',
                    (string) ($row['applied_at'] ?? ''),
                    $row['checksum_matches'] === null ? '-' : (!empty($row['checksum_matches']) ? 'yes' : 'no'),
                ];
            }, $rows)
        );

        return self::SUCCESS;
    }
}
