<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use WrkAndreev\EvocmsTemplateRegistry\Support\TemplateRegistryPathResolver;

class MakeTemplateRegistryMigrationCommand extends Command
{
    protected $signature = 'template-registry:migrate:make {name : Migration name}';

    protected $description = 'Create a new template registry content migration';

    public function handle(): int
    {
        $config = (array) config('template-registry', []);
        $dir = (new TemplateRegistryPathResolver())->migrationsPath($config);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->error('Failed to create migrations directory: ' . $dir);
            return self::FAILURE;
        }

        $slug = Str::snake((string) $this->argument('name'));
        $file = $dir . '/' . date('Y_m_d_His') . '_' . $slug . '.php';
        $name = basename($file, '.php');

        $template = <<<'PHP'
<?php

declare(strict_types=1);

return [
    'name' => '%s',
    'description' => '%s',
    'operations' => [
        [
            'op' => 'upsert_template',
            'match' => ['alias' => 'example'],
            'data' => [
                'name' => 'Example',
                'alias' => 'example',
            ],
        ],
    ],
];
PHP;

        file_put_contents($file, sprintf($template, $name, (string) $this->argument('name')) . PHP_EOL);
        $this->info('Created migration: ' . $file);

        return self::SUCCESS;
    }
}
