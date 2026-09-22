<?php

declare(strict_types=1);

namespace Indieinabox\Database\Migrations;

use Indieinabox\Database\Contracts\MigrationInterface;
use PDO;

/**
 * Migration creating Phase 29 tables: activitypub_activities, flowerbed_followers, and federation_blocks.
 */
class M20260922000001CreatePhase29Tables implements MigrationInterface
{
    public function up(PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS activitypub_activities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            activity_id TEXT UNIQUE NOT NULL,
            type TEXT NOT NULL,
            actor TEXT NOT NULL,
            payload_json TEXT NOT NULL,
            created_at INTEGER NOT NULL
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS flowerbed_followers (
            flowerbed_slug TEXT NOT NULL,
            actor_url TEXT NOT NULL,
            inbox_url TEXT NOT NULL,
            shared_inbox_url TEXT,
            created_at INTEGER NOT NULL,
            PRIMARY KEY (flowerbed_slug, actor_url)
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS federation_blocks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain TEXT,
            actor_url TEXT,
            action TEXT NOT NULL DEFAULT \'block\',
            reason TEXT,
            created_at INTEGER NOT NULL
        )');
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS federation_blocks');
        $db->exec('DROP TABLE IF EXISTS flowerbed_followers');
        $db->exec('DROP TABLE IF EXISTS activitypub_activities');
    }
}
