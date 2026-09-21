<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Core\Database;
use Indieinabox\Database\MigrationRunner;
use Throwable;

/**
 * Command to execute schema migrations, roll back batches, or view migration status.
 */
class MigrateCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'migrate';
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    public function getAliases(): array
    {
        return ['migrations', 'migrate:status', 'migrate:rollback'];
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Executes pending database migrations, rolls back batches, or inspects status.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return "php indieinabox.php migrate [--status] [--rollback] [--steps=N]";
    }

    #[\Override]
    public function execute(array $argv): int
    {
        if (!Database::isConnected()) {
            $dataDir = $this->site->paths->dataDir ?? (getcwd() . '/data');
            $dbPath = rtrim($dataDir, '/\\') . '/indieinabox.sqlite';
            Database::connect($dbPath);
        }

        $db = Database::getDb();
        $runner = new MigrationRunner($db);

        $action = $argv[1] ?? 'migrate';
        $isStatus = $action === 'migrate:status' || in_array('--status', $argv, true);
        $isRollback = $action === 'migrate:rollback' || in_array('--rollback', $argv, true);

        try {
            if ($isStatus) {
                return $this->showStatus($runner);
            }

            if ($isRollback) {
                return $this->runRollback($runner, $argv);
            }

            return $this->runMigrate($runner);
        } catch (Throwable $e) {
            echo "\033[31m[ERROR] Migration operation failed: " . $e->getMessage() . "\033[0m\n";
            return 1;
        }
    }

    private function runMigrate(MigrationRunner $runner): int
    {
        echo "Running database migrations...\n";
        $migrated = $runner->migrate();

        if (empty($migrated)) {
            echo "Database schema is already up to date. Nothing to migrate.\n";
            return 0;
        }

        foreach ($migrated as $name) {
            echo "  \033[32m✔ Migrated:\033[0m {$name}\n";
        }

        echo "Migration completed successfully (" . count($migrated) . " migration(s) applied).\n";
        return 0;
    }

    /**
     * @param array<int, string> $argv
     */
    private function runRollback(MigrationRunner $runner, array $argv): int
    {
        $steps = null;
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--steps=')) {
                $steps = (int) substr($arg, 8);
                break;
            }
        }

        echo "Rolling back migrations" . ($steps ? " ({$steps} steps)" : " (last batch)") . "...\n";
        $rolledBack = $runner->rollback($steps);

        if (empty($rolledBack)) {
            echo "No migrations found to roll back.\n";
            return 0;
        }

        foreach ($rolledBack as $name) {
            echo "  \033[33m↩ Rolled back:\033[0m {$name}\n";
        }

        echo "Rollback completed successfully (" . count($rolledBack) . " migration(s) reverted).\n";
        return 0;
    }

    private function showStatus(MigrationRunner $runner): int
    {
        $status = $runner->status();

        echo "\n=== Database Migration Status ===\n";
        printf("%-55s | %-6s | %-19s | %-8s\n", "Migration", "Batch", "Executed At", "Status");
        echo str_repeat('-', 95) . "\n";

        foreach ($status as $row) {
            $batchStr = $row['batch'] !== null ? (string) $row['batch'] : '-';
            $executedAt = $row['executed_at'] ?? '-';
            $statusStr = $row['status'] === 'applied' ? "\033[32mapplied\033[0m" : "\033[33mpending\033[0m";

            printf("%-55s | %-6s | %-19s | %s\n", $row['migration'], $batchStr, $executedAt, $statusStr);
        }

        echo str_repeat('-', 95) . "\n\n";
        return 0;
    }
}
