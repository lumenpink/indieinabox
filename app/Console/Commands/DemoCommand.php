<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Demo\DemoService;

/**
 * CLI command to manage public interactive demo sandbox environments.
 */
class DemoCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'demo';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Manages the interactive demo sandbox environment (seed, reset, status).';
    }

    #[\Override]
    public function getUsage(): string
    {
        return 'php indieinabox.php demo [seed|reset|status] [options]';
    }

    #[\Override]
    public function execute(array $argv): int
    {
        $subcommand = $argv[2] ?? 'status';
        $service = new DemoService($this->site);

        switch ($subcommand) {
            case 'seed':
                $fqdn = $this->getOption($argv, 'fqdn') ?? 'https://demo.indieinabox.2lp.in';
                $sitename = $this->getOption($argv, 'sitename') ?? 'IndieInABox Public Sandbox';
                $password = $this->getOption($argv, 'password') ?? 'demo123';
                $author = $this->getOption($argv, 'author') ?? 'Demo Admin';

                echo "🌱 Seeding demo sandbox environment...\n";
                $result = $service->seed([
                    'fqdn' => $fqdn,
                    'sitename' => $sitename,
                    'password' => $password,
                    'author' => $author,
                ]);

                if (!empty($result['success'])) {
                    echo "✅ Demo sandbox successfully seeded and golden snapshot created!\n";
                    echo "   URL:      {$fqdn}\n";
                    echo "   Admin:    admin\n";
                    echo "   Password: {$password}\n";
                    return 0;
                }

                echo "❌ Failed to seed demo environment.\n";
                return 1;

            case 'reset':
                echo "🔄 Rolling back demo sandbox to golden snapshot...\n";
                $result = $service->reset();
                if (!empty($result['success'])) {
                    echo "✅ Demo sandbox restored successfully!\n";
                    return 0;
                }

                echo "❌ Demo reset failed: " . ($result['error'] ?? 'Unknown error') . "\n";
                return 1;

            case 'status':
            default:
                $status = $service->getStatus();
                echo "ℹ️  IndieInABox Demo Sandbox Status:\n";
                echo "   Demo Mode:      " . ($status['demo_mode'] ? 'ACTIVE' : 'INACTIVE') . "\n";
                echo "   Reset Interval: {$status['interval_seconds']} seconds\n";
                echo "   Last Reset:     " . ($status['last_reset_iso'] ?? 'Never') . "\n";
                echo "   Next Reset:     " . ($status['next_reset_iso'] ?? 'N/A') . "\n";
                echo "   Reset Due:      " . ($service->shouldReset() ? 'YES' : 'NO') . "\n";
                return 0;
        }
    }
}
