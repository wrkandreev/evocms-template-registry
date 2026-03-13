<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Console;

use Illuminate\Console\Command;
use RuntimeException;
use WrkAndreev\EvocmsTemplateRegistry\Services\TemplateRegistryGenerator;

class GenerateTemplateRegistryCommand extends Command
{
    protected $signature = 'template-registry:generate
        {--output= : Output directory}
        {--format= : json|md|php|all}
        {--strict : Non-zero exit code if controller/view is missing}';

    protected $description = 'Generate templates registry: template -> controller -> view -> TVs';

    public function handle(): int
    {
        $config = (array) config('template-registry', []);

        $format = strtolower((string) ($this->option('format') ?: ($config['format'] ?? 'all')));
        if (!in_array($format, ['json', 'md', 'php', 'all'], true)) {
            $this->error('Invalid --format. Allowed: json|md|php|all');
            return self::INVALID;
        }

        $output = trim((string) ($this->option('output') ?: ($config['output'] ?? '')));
        if ($output === '') {
            $output = 'core/custom/packages/Main/generated/registry';
        }

        $strict = (bool) $this->option('strict') || (bool) ($config['strict'] ?? false);

        $generator = new TemplateRegistryGenerator($config);

        try {
            $payload = $generator->buildPayload();
            $written = $generator->writePayload($payload, $output, $format);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        foreach ($written as $file) {
            $this->info('Generated: ' . $file);
        }

        if ($strict && (($payload['stats']['missing_controllers'] ?? 0) > 0 || ($payload['stats']['missing_views'] ?? 0) > 0)) {
            $this->error('Strict mode failed: missing controller/view found.');
            return 2;
        }

        return self::SUCCESS;
    }
}
