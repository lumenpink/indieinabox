<?php

declare(strict_types=1);

namespace Indieinabox\Database;

use Indieinabox\Database\Contracts\MigrationInterface;
use PDO;
use RuntimeException;

/**
 * Orchestrator for database migrations discovery, execution, rollback, and status reporting.
 */
class MigrationRunner
{
    private PDO $db;

    /**
     * @var array<string, class-string<MigrationInterface>>
     */
    private array $migrations;

    /**
     * @param PDO $db
     * @param array<string, class-string<MigrationInterface>>|null $migrations
     */
    public function __construct(PDO $db, ?array $migrations = null)
    {
        $this->db = $db;
        $this->migrations = $migrations ?? MigrationRegistry::getMigrations();
        $this->ensureMigrationsTable();
    }

    /**
     * Creates the migrations tracking table if it does not already exist.
     */
    public function ensureMigrationsTable(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration TEXT NOT NULL UNIQUE,
            batch INTEGER NOT NULL,
            executed_at DATETIME NOT NULL
        )');
    }

    /**
     * Retrieves all migrations recorded in the database.
     *
     * @return array<string, array{id: int, migration: string, batch: int, executed_at: string}>
     */
    public function getExecutedMigrations(): array
    {
        $stmt = $this->db->query('SELECT id, migration, batch, executed_at FROM migrations ORDER BY id ASC');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $result = [];
        foreach ($rows as $row) {
            $name = (string) $row['migration'];
            $result[$name] = [
                'id' => (int) $row['id'],
                'migration' => $name,
                'batch' => (int) $row['batch'],
                'executed_at' => (string) $row['executed_at'],
            ];
        }

        return $result;
    }

    /**
     * Returns all migrations that have not yet been executed.
     *
     * @return array<string, class-string<MigrationInterface>>
     */
    public function getPendingMigrations(): array
    {
        $executed = $this->getExecutedMigrations();
        $pending = [];

        foreach ($this->migrations as $name => $class) {
            if (!isset($executed[$name])) {
                $pending[$name] = $class;
            }
        }

        return $pending;
    }

    /**
     * Calculates the next execution batch number.
     */
    public function getNextBatchNumber(): int
    {
        $stmt = $this->db->query('SELECT MAX(batch) AS max_batch FROM migrations');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $max = $row && isset($row['max_batch']) ? (int) $row['max_batch'] : 0;

        return $max + 1;
    }

    /**
     * Executes all pending migrations forward.
     *
     * @return array<int, string> List of migrated migration identifiers.
     */
    public function migrate(): array
    {
        $pending = $this->getPendingMigrations();
        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();
        $migrated = [];

        foreach ($pending as $name => $class) {
            if (!class_exists($class)) {
                throw new RuntimeException("Migration class '{$class}' not found for '{$name}'.");
            }

            /** @var MigrationInterface $instance */
            $instance = new $class();
            if (!$instance instanceof MigrationInterface) {
                throw new RuntimeException("Migration class '{$class}' must implement MigrationInterface.");
            }

            $this->db->beginTransaction();
            try {
                $instance->up($this->db);

                $stmt = $this->db->prepare('INSERT INTO migrations (migration, batch, executed_at) VALUES (?, ?, ?)');
                $stmt->execute([$name, $batch, gmdate('Y-m-d H:i:s')]);

                $this->db->commit();
                $migrated[] = $name;
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw new RuntimeException("Migration '{$name}' failed: " . $e->getMessage(), (int) $e->getCode(), $e);
            }
        }

        return $migrated;
    }

    /**
     * Rolls back the last batch of migrations or a specific number of steps.
     *
     * @param int|null $steps Optional step count to revert. If null, reverts the entire last batch.
     * @return array<int, string> List of rolled back migration identifiers.
     */
    public function rollback(?int $steps = null): array
    {
        if ($steps !== null && $steps > 0) {
            $stmt = $this->db->prepare('SELECT id, migration FROM migrations ORDER BY id DESC LIMIT ?');
            $stmt->bindValue(1, $steps, PDO::PARAM_INT);
            $stmt->execute();
            $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->db->query('SELECT id, migration FROM migrations WHERE batch = (SELECT MAX(batch) FROM migrations) ORDER BY id DESC');
            $targets = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        if (empty($targets)) {
            return [];
        }

        $rolledBack = [];

        foreach ($targets as $target) {
            $id = (int) $target['id'];
            $name = (string) $target['migration'];

            $class = $this->migrations[$name] ?? null;
            if ($class === null || !class_exists($class)) {
                throw new RuntimeException("Cannot rollback '{$name}': class not found in registry.");
            }

            /** @var MigrationInterface $instance */
            $instance = new $class();
            if (!$instance instanceof MigrationInterface) {
                throw new RuntimeException("Migration class '{$class}' must implement MigrationInterface.");
            }

            $this->db->beginTransaction();
            try {
                $instance->down($this->db);

                $delStmt = $this->db->prepare('DELETE FROM migrations WHERE id = ?');
                $delStmt->execute([$id]);

                $this->db->commit();
                $rolledBack[] = $name;
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw new RuntimeException("Rollback of '{$name}' failed: " . $e->getMessage(), (int) $e->getCode(), $e);
            }
        }

        return $rolledBack;
    }

    /**
     * Returns a summary status for all registered migrations.
     *
     * @return array<int, array{migration: string, batch: ?int, executed_at: ?string, status: string}>
     */
    public function status(): array
    {
        $executed = $this->getExecutedMigrations();
        $list = [];

        foreach ($this->migrations as $name => $_class) {
            if (isset($executed[$name])) {
                $list[] = [
                    'migration' => $name,
                    'batch' => $executed[$name]['batch'],
                    'executed_at' => $executed[$name]['executed_at'],
                    'status' => 'applied',
                ];
            } else {
                $list[] = [
                    'migration' => $name,
                    'batch' => null,
                    'executed_at' => null,
                    'status' => 'pending',
                ];
            }
        }

        return $list;
    }
}
