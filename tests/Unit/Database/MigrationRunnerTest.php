<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Indieinabox\Database\Contracts\MigrationInterface;
use Indieinabox\Database\MigrationRunner;
use PDO;

beforeEach(function () {
    $this->db = new PDO('sqlite::memory:');
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Dummy test migrations
    $this->testMigrations = [
        '2026_01_01_000001_create_test_alpha' => new class implements MigrationInterface {
            public function up(PDO $db): void
            {
                $db->exec('CREATE TABLE test_alpha (id INTEGER PRIMARY KEY, name TEXT)');
            }
            public function down(PDO $db): void
            {
                $db->exec('DROP TABLE IF EXISTS test_alpha');
            }
        },
        '2026_01_01_000002_create_test_beta' => new class implements MigrationInterface {
            public function up(PDO $db): void
            {
                $db->exec('CREATE TABLE test_beta (id INTEGER PRIMARY KEY, val TEXT)');
            }
            public function down(PDO $db): void
            {
                $db->exec('DROP TABLE IF EXISTS test_beta');
            }
        },
    ];
});

test('MigrationRunner creates migrations tracking table on instantiation', function () {
    $runner = new MigrationRunner($this->db, []);
    $tables = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'")->fetchAll();
    expect($tables)->toHaveCount(1);
});

test('MigrationRunner executes pending migrations and records batches', function () {
    $map = [
        '2026_01_01_000001_create_test_alpha' => get_class($this->testMigrations['2026_01_01_000001_create_test_alpha']),
        '2026_01_01_000002_create_test_beta' => get_class($this->testMigrations['2026_01_01_000002_create_test_beta']),
    ];

    $runner = new MigrationRunner($this->db, $map);
    expect($runner->getPendingMigrations())->toHaveCount(2);

    $migrated = $runner->migrate();
    expect($migrated)->toBe([
        '2026_01_01_000001_create_test_alpha',
        '2026_01_01_000002_create_test_beta',
    ]);

    // Verify tables created
    $alpha = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_alpha'")->fetchAll();
    $beta = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_beta'")->fetchAll();
    expect($alpha)->toHaveCount(1);
    expect($beta)->toHaveCount(1);

    // Verify pending is now empty
    expect($runner->getPendingMigrations())->toBeEmpty();
    expect($runner->migrate())->toBeEmpty();

    // Verify status
    $status = $runner->status();
    expect($status)->toHaveCount(2);
    expect($status[0]['status'])->toBe('applied');
    expect($status[0]['batch'])->toBe(1);
    expect($status[1]['status'])->toBe('applied');
    expect($status[1]['batch'])->toBe(1);
});

test('MigrationRunner rolls back the last batch', function () {
    $map = [
        '2026_01_01_000001_create_test_alpha' => get_class($this->testMigrations['2026_01_01_000001_create_test_alpha']),
        '2026_01_01_000002_create_test_beta' => get_class($this->testMigrations['2026_01_01_000002_create_test_beta']),
    ];

    $runner = new MigrationRunner($this->db, $map);
    $runner->migrate();

    $rolledBack = $runner->rollback();
    expect($rolledBack)->toBe([
        '2026_01_01_000002_create_test_beta',
        '2026_01_01_000001_create_test_alpha',
    ]);

    $alpha = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_alpha'")->fetchAll();
    $beta = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_beta'")->fetchAll();
    expect($alpha)->toBeEmpty();
    expect($beta)->toBeEmpty();
    expect($runner->getPendingMigrations())->toHaveCount(2);
});

test('MigrationRunner runs core default migrations cleanly', function () {
    $runner = new MigrationRunner($this->db);
    $migrated = $runner->migrate();

    expect($migrated)->toHaveCount(6);

    // Verify core tables exist
    $tables = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    expect($tables)->toContain('settings');
    expect($tables)->toContain('kinds');
    expect($tables)->toContain('translations');
    expect($tables)->toContain('url_translations');
    expect($tables)->toContain('indieauth_codes');
    expect($tables)->toContain('indieauth_tokens');
    expect($tables)->toContain('microsub_channels');
    expect($tables)->toContain('microsub_subscriptions');
    expect($tables)->toContain('microsub_filters');
    expect($tables)->toContain('activitypub_followers');
    expect($tables)->toContain('activitypub_keys');
    expect($tables)->toContain('activitypub_outbox');
    expect($tables)->toContain('activitypub_actors');
    expect($tables)->toContain('inbox_queue');
    expect($tables)->toContain('archive_queue');
    expect($tables)->toContain('archived_links');
    expect($tables)->toContain('archive_aliases');
    expect($tables)->toContain('push_subscriptions');

    // Verify seeds were inserted
    $setting = $this->db->query("SELECT value FROM settings WHERE key='sitename'")->fetchColumn();
    expect($setting)->toBe('A simple generic blog');

    $channels = $this->db->query("SELECT uid FROM microsub_channels")->fetchAll(PDO::FETCH_COLUMN);
    expect($channels)->toContain('inbox');
    expect($channels)->toContain('notifications');
});
