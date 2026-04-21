<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BLangLexiconService
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** @return array<int,array<string,mixed>> */
    public function listEntries(int $limit = 500): array
    {
        $table = $this->requireLexiconTable();
        $limit = max(1, min($limit, 1000));
        $languages = $this->languages();
        $select = array_values(array_filter(array_merge(['id', 'name'], $languages), fn (string $column): bool => Schema::hasColumn($table, $column)));

        $rows = DB::table($table)
            ->select($select)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->mapEntry($row, $languages);
        }

        return $result;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createEntry(array $input): array
    {
        $table = $this->requireLexiconTable();
        $languages = $this->languages();
        $name = $this->requireName($input);

        if ($this->valueExists($table, 'name', $name)) {
            throw new RuntimeException('bLang lexicon key already exists.');
        }

        $payload = ['name' => $name] + $this->normalizeValuesPayload($input, $languages, $table);
        $id = (int) DB::table($table)->insertGetId($payload);

        return [
            'entity' => 'blang_lexicon_entry',
            'id' => $id,
            'message' => 'bLang lexicon entry created.',
            'entry' => $this->findEntry($id),
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateEntry(int $entryId, array $input): array
    {
        $table = $this->requireLexiconTable();
        $this->assertExists($table, $entryId, 'bLang lexicon entry');
        $languages = $this->languages();
        $payload = [];

        if (array_key_exists('name', $input)) {
            $name = $this->requireName($input);
            if ($this->valueExistsExceptId($table, 'name', $name, $entryId)) {
                throw new RuntimeException('bLang lexicon key already exists.');
            }
            $payload['name'] = $name;
        }

        $payload += $this->normalizeValuesPayload($input, $languages, $table);

        if ($payload === []) {
            throw new RuntimeException('No bLang lexicon fields provided for update.');
        }

        DB::table($table)->where('id', $entryId)->update($payload);

        return [
            'entity' => 'blang_lexicon_entry',
            'id' => $entryId,
            'message' => 'bLang lexicon entry updated.',
            'entry' => $this->findEntry($entryId),
        ];
    }

    /** @return array<string,mixed> */
    public function deleteEntry(int $entryId): array
    {
        $table = $this->requireLexiconTable();
        $entry = $this->findEntry($entryId);
        if ($entry === null) {
            throw new RuntimeException('bLang lexicon entry not found.');
        }

        DB::table($table)->where('id', $entryId)->delete();

        return [
            'entity' => 'blang_lexicon_entry',
            'id' => $entryId,
            'message' => 'bLang lexicon entry deleted.',
            'entry' => $entry,
        ];
    }

    /** @return array<string,mixed>|null */
    private function findEntry(int $entryId): ?array
    {
        $table = $this->requireLexiconTable();
        $languages = $this->languages();
        $select = array_values(array_filter(array_merge(['id', 'name'], $languages), fn (string $column): bool => Schema::hasColumn($table, $column)));
        $row = DB::table($table)->select($select)->where('id', $entryId)->first();

        return $row === null ? null : $this->mapEntry($row, $languages);
    }

    /** @param object $row @param array<int,string> $languages @return array<string,mixed> */
    private function mapEntry(object $row, array $languages): array
    {
        $values = [];
        foreach ($languages as $language) {
            $values[$language] = property_exists($row, $language) ? (string) ($row->{$language} ?? '') : '';
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => (string) ($row->name ?? ''),
            'values' => $values,
        ];
    }

    /** @param array<string,mixed> $input @param array<int,string> $languages @return array<string,string> */
    private function normalizeValuesPayload(array $input, array $languages, string $table): array
    {
        $payload = [];
        $values = isset($input['values']) && is_array($input['values']) ? $input['values'] : [];

        foreach ($languages as $language) {
            if (!Schema::hasColumn($table, $language)) {
                continue;
            }

            if (array_key_exists($language, $values)) {
                $payload[$language] = (string) ($values[$language] ?? '');
                continue;
            }

            if (array_key_exists($language, $input)) {
                $payload[$language] = (string) ($input[$language] ?? '');
            }
        }

        return $payload;
    }

    /** @param array<string,mixed> $input */
    private function requireName(array $input): string
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Required field "name" is empty.');
        }

        return $name;
    }

    /** @return array<int,string> */
    private function languages(): array
    {
        $extractor = new BLangExtractor($this->config, $this->projectRoot());
        return array_values(array_filter((array) ($extractor->extract()['languages'] ?? []), static fn (mixed $lang): bool => is_string($lang) && trim($lang) !== ''));
    }

    private function projectRoot(): string
    {
        $basePath = function_exists('base_path') ? (string) base_path() : (getcwd() ?: '');
        return dirname($basePath);
    }

    private function requireLexiconTable(): string
    {
        $table = $this->resolveTableName((string) $this->cfg('blang.lexicon_table', 'blang'));
        if ($table === null) {
            throw new RuntimeException('bLang lexicon table not found.');
        }

        return $table;
    }

    private function resolveTableName(string $base): ?string
    {
        if ($base === '') {
            return null;
        }

        if (Schema::hasTable($base)) {
            return $base;
        }

        $defaultConnection = (string) \config('database.default');
        $prefix = (string) \config("database.connections.{$defaultConnection}.prefix", '');
        if ($prefix !== '') {
            $withPrefix = $prefix . $base;
            if (Schema::hasTable($withPrefix)) {
                return $withPrefix;
            }
        }

        return null;
    }

    private function assertExists(string $table, int $id, string $label): void
    {
        if ($id <= 0) {
            throw new RuntimeException($label . ' id must be greater than zero.');
        }

        if (!DB::table($table)->where('id', $id)->exists()) {
            throw new RuntimeException($label . ' not found.');
        }
    }

    private function valueExists(string $table, string $column, string $value): bool
    {
        if (!Schema::hasColumn($table, $column)) {
            return false;
        }

        return DB::table($table)->where($column, $value)->exists();
    }

    private function valueExistsExceptId(string $table, string $column, string $value, int $id): bool
    {
        if (!Schema::hasColumn($table, $column)) {
            return false;
        }

        return DB::table($table)
            ->where($column, $value)
            ->where('id', '!=', $id)
            ->exists();
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        $current = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}
