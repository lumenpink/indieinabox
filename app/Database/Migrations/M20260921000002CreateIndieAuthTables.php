<?php

declare(strict_types=1);

namespace Indieinabox\Database\Migrations;

use Indieinabox\Database\Contracts\MigrationInterface;
use PDO;

/**
 * Migration creating IndieAuth authorization codes and tokens tables.
 */
class M20260921000002CreateIndieAuthTables implements MigrationInterface
{
    public function up(PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS indieauth_codes (
            code_hash TEXT PRIMARY KEY,
            client_id TEXT NOT NULL,
            redirect_uri TEXT NOT NULL,
            state TEXT,
            scope TEXT,
            code_challenge TEXT,
            code_challenge_method TEXT,
            expires_at INTEGER NOT NULL,
            me TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE IF NOT EXISTS indieauth_tokens (
            token_hash TEXT PRIMARY KEY,
            client_id TEXT NOT NULL,
            scope TEXT,
            me TEXT NOT NULL,
            created_at INTEGER NOT NULL
        )');
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS indieauth_tokens');
        $db->exec('DROP TABLE IF EXISTS indieauth_codes');
    }
}
