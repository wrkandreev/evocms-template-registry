<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Console;

use Illuminate\Console\Command;
use WrkAndreev\EvocmsTemplateRegistry\Support\RouteBridgeManager;

class UninstallTemplateRegistryRoutesCommand extends Command
{
    protected $signature = 'template-registry:routes:uninstall';

    protected $description = 'Remove Template Registry web route bridge from core/custom/routes.php';

    public function handle(): int
    {
        $result = (new RouteBridgeManager())->uninstall();
        $message = (bool) ($result['changed'] ?? false)
            ? 'Removed route bridge: '
            : 'Route bridge not found: ';

        $this->info($message . (string) ($result['path'] ?? ''));

        return self::SUCCESS;
    }
}
