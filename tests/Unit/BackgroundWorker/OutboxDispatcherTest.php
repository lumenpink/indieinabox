<?php

declare(strict_types=1);

use Indieinabox\BackgroundWorker\OutboxDispatcher;
use Indieinabox\Site;
use Indieinabox\Core\Database;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/ap_outbox_test_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $this->db = Database::getDb();
    $schema = file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    $this->db->exec($schema);

    $this->site = new Site();
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('OutboxDispatcher stops when no RSA key is found in database', function () {
    $dispatcher = new OutboxDispatcher($this->site, $this->db);
    ob_start();
    $dispatcher->process();
    $output = ob_get_clean();

    expect($output)->toContain('No RSA key found. Exiting.');
});

test('OutboxDispatcher exits gracefully when key exists but no pending messages', function () {
    $this->db->prepare("INSERT INTO activitypub_keys (key_id, public_key, private_key, created_at) VALUES ('main-key', 'pub', 'priv', ?)")
        ->execute([time()]);

    $dispatcher = new OutboxDispatcher($this->site, $this->db);
    ob_start();
    $dispatcher->process();
    $output = ob_get_clean();

    expect($output)->toContain('No pending messages.');
});

test('OutboxDispatcher prunes old messages and actors', function () {
    $this->db->prepare("INSERT INTO activitypub_keys (key_id, public_key, private_key, created_at) VALUES ('main-key', 'pub', 'priv', ?)")
        ->execute([time()]);

    $oldTime = time() - (10 * 86400);
    $this->db->prepare("INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) VALUES ('{}', 'https://inbox', 'sent', ?)")
        ->execute([$oldTime]);
    $this->db->prepare("INSERT INTO activitypub_actors (actor_url, public_key, updated_at) VALUES ('https://actor', 'pub', ?)")
        ->execute([time() - (35 * 86400)]);

    $dispatcher = new OutboxDispatcher($this->site, $this->db);
    ob_start();
    $dispatcher->process();
    ob_get_clean();

    $outboxCount = $this->db->query("SELECT COUNT(*) FROM activitypub_outbox")->fetchColumn();
    $actorCount = $this->db->query("SELECT COUNT(*) FROM activitypub_actors")->fetchColumn();

    expect((int)$outboxCount)->toBe(0);
    expect((int)$actorCount)->toBe(0);
});
