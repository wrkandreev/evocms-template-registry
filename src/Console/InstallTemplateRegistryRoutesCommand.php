<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Console;

use Illuminate\Console\Command;
use WrkAndreev\EvocmsTemplateRegistry\Support\RouteBridgeManager;

class InstallTemplateRegistryRoutesCommand extends Command
{
    protected $signature = 'template-registry:routes:install';

    protected $description = 'Install Template Registry web route bridge into core/custom/routes.php';

    public function handle(): int
    {
        $result = (new RouteBridgeManager())->install();
        $message = (bool) ($result['changed'] ?? false)
            ? 'Installed route bridge: '
            : 'Route bridge already installed: ';

        $this->info($message . (string) ($result['path'] ?? ''));

        return self::SUCCESS;
    }
}
