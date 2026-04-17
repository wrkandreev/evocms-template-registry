<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TemplateRegistryMigrationStateRepository
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function ensureTable(): void
    {
        $table = $this->tableName();
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function ($table): void {
            $table->bigIncrements('id');
            $table->string('name', 190)->unique();
            $table->string('checksum', 64);
            $table->timestamp('applied_at')->nullable();
        });
    }

    /** @return array<string,array{name:string,checksum:string,applied_at:string|null}> */
    public function allApplied(): array
    {
        $this->ensureTable();

        $rows = DB::table($this->tableName())
            ->select(['name', 'checksum', 'applied_at'])
            ->orderBy('name')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $name = (string) ($row->name ?? '');
            if ($name === '') {
                continue;
            }

            $result[$name] = [
                'name' => $name,
                'checksum' => (string) ($row->checksum ?? ''),
                'applied_at' => isset($row->applied_at) ? (string) $row->applied_at : null,
            ];
        }

        return $result;
    }

    public function markApplied(string $name, string $checksum): void
    {
        $this->ensureTable();

        DB::table($this->tableName())->updateOrInsert(
            ['name' => $name],
            [
                'checksum' => $checksum,
                'applied_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    private function tableName(): string
    {
        return (string) ($this->config['migrations']['table'] ?? 'template_registry_migrations');
    }
}
