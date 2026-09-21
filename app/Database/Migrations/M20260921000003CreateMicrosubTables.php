<?php

declare(strict_types=1);

namespace Indieinabox\Database\Migrations;

use Indieinabox\Database\Contracts\MigrationInterface;
use PDO;

/**
 * Migration creating Microsub channels, subscriptions, and filtering rules tables.
 */
class M20260921000003CreateMicrosubTables implements MigrationInterface
{
    public function up(PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS microsub_channels (
            uid TEXT PRIMARY KEY,
            name TEXT
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS microsub_subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            channel_uid TEXT,
            url TEXT,
            type TEXT,
            name TEXT,
            photo TEXT
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS microsub_filters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filter_type TEXT NOT NULL,
            content TEXT NOT NULL,
            action TEXT NOT NULL DEFAULT \'mute\',
            channel_uid TEXT DEFAULT \'*\',
            created_at INTEGER NOT NULL
        )');

        // Seed default channels
        $db->exec("INSERT OR IGNORE INTO microsub_channels (uid, name) VALUES ('inbox', 'Timeline')");
        $db->exec("INSERT OR IGNORE INTO microsub_channels (uid, name) VALUES ('notifications', 'Notifications')");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS microsub_filters');
        $db->exec('DROP TABLE IF EXISTS microsub_subscriptions');
        $db->exec('DROP TABLE IF EXISTS microsub_channels');
    }
}
