<?php

declare(strict_types=1);

namespace Indieinabox\Database\Migrations;

use Indieinabox\Database\Contracts\MigrationInterface;
use PDO;

/**
 * Migration creating Web Push notification browser subscriptions table.
 */
class M20260921000006CreatePushSubscriptionsTable implements MigrationInterface
{
    public function up(PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            endpoint TEXT NOT NULL UNIQUE,
            p256dh TEXT NOT NULL,
            auth TEXT NOT NULL,
            user_agent TEXT,
            created_at INTEGER NOT NULL
        )');
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS push_subscriptions');
    }
}
