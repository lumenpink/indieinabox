<?php

declare(strict_types=1);

namespace Indieinabox\Database\Migrations;

use Indieinabox\Database\Contracts\MigrationInterface;
use PDO;

/**
 * Migration creating background inbox processor queue and web archiving snapshot tables.
 */
class M20260921000005CreateArchiveAndQueueTables implements MigrationInterface
{
    public function up(PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS inbox_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            payload_json TEXT NOT NULL,
            created_at INTEGER NOT NULL
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS archive_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL,
            status TEXT DEFAULT \'pending\',
            requested_at INTEGER NOT NULL,
            force_archive INTEGER DEFAULT 0
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS archived_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL,
            timestamp INTEGER NOT NULL,
            local_pdf_path TEXT,
            archive_org_url TEXT
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS archive_aliases (
            original_url TEXT PRIMARY KEY,
            final_url TEXT NOT NULL
        )');
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS archive_aliases');
        $db->exec('DROP TABLE IF EXISTS archived_links');
        $db->exec('DROP TABLE IF EXISTS archive_queue');
        $db->exec('DROP TABLE IF EXISTS inbox_queue');
    }
}
