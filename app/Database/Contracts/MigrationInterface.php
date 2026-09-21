<?php

declare(strict_types=1);

namespace Indieinabox\Database\Contracts;

use PDO;

/**
 * Contract for database schema migrations.
 */
interface MigrationInterface
{
    /**
     * Executes the forward migration changes against the database.
     */
    public function up(PDO $db): void;

    /**
     * Rolls back the migration changes from the database.
     */
    public function down(PDO $db): void;
}
