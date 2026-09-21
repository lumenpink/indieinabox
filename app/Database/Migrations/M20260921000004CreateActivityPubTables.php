<?php

declare(strict_types=1);

namespace Indieinabox\Database\Migrations;

use Indieinabox\Database\Contracts\MigrationInterface;
use PDO;

/**
 * Migration creating ActivityPub followers, cryptographic keys, outbox delivery queue, and actors cache tables.
 */
class M20260921000004CreateActivityPubTables implements MigrationInterface
{
    public function up(PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS activitypub_followers (
            actor_url TEXT PRIMARY KEY,
            inbox_url TEXT NOT NULL,
            shared_inbox_url TEXT
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS activitypub_keys (
            key_id TEXT PRIMARY KEY,
            private_key TEXT NOT NULL,
            public_key TEXT NOT NULL,
            created_at INTEGER NOT NULL
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS activitypub_outbox (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            payload_json TEXT NOT NULL,
            target_inbox TEXT NOT NULL,
            status TEXT DEFAULT \'pending\',
            created_at INTEGER NOT NULL
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS activitypub_actors (
            actor_url TEXT PRIMARY KEY,
            public_key TEXT NOT NULL,
            updated_at INTEGER NOT NULL
        )');

        $db->exec("INSERT OR REPLACE INTO settings (key, value) VALUES ('activitypub_handle', 'author')");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS activitypub_actors');
        $db->exec('DROP TABLE IF EXISTS activitypub_outbox');
        $db->exec('DROP TABLE IF EXISTS activitypub_keys');
        $db->exec('DROP TABLE IF EXISTS activitypub_followers');
    }
}
