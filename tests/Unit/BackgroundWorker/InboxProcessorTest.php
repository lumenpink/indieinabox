<?php

declare(strict_types=1);

use Indieinabox\BackgroundWorker\InboxProcessor;
use Indieinabox\Site;
use Indieinabox\Database;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/inbox_proc_test_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $this->db = Database::getDb();
    $schema = file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    $this->db->exec($schema);

    $this->site = new Site();
    $this->site->metadata->fqdn = 'https://example.com';
    $this->site->paths->baseDir = $this->tempDir;
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('InboxProcessor exits cleanly when inbox queue is empty', function () {
    $processor = new InboxProcessor($this->site, $this->db);
    ob_start();
    $processor->process();
    $output = ob_get_clean();

    expect($output)->toContain('No inbox items.');
});

test('InboxProcessor processes Follow ActivityPub activity and enqueues Accept', function () {
    $followPayload = [
        'headers' => [
            'signature' => 'keyId="https://remote.social/actor#main-key",headers="(request-target) host date",signature="dummy"'
        ],
        'body' => json_encode([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://remote.social/actor',
            'object' => 'https://example.com/actor'
        ]),
        'method' => 'POST',
        'path' => '/inbox'
    ];

    $this->db->prepare("INSERT INTO inbox_queue (type, payload_json, created_at) VALUES ('activitypub', ?, ?)")
        ->execute([json_encode($followPayload), time()]);

    $jsonFetcher = function (string $url) {
        return [
            'publicKey' => [
                'id' => 'https://remote.social/actor#main-key',
                'publicKeyPem' => 'dummy-pem'
            ]
        ];
    };

    $processor = new InboxProcessor($this->site, $this->db, null, $jsonFetcher);
    ob_start();
    $processor->process();
    ob_get_clean();

    // Check follower recorded
    $follower = $this->db->query("SELECT actor_url, inbox_url FROM activitypub_followers LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    expect($follower['actor_url'])->toBe('https://remote.social/actor');
    expect($follower['inbox_url'])->toBe('https://remote.social/actor/inbox');

    // Check outbox has Accept queued
    $outbox = $this->db->query("SELECT target_inbox, payload_json FROM activitypub_outbox LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    expect($outbox['target_inbox'])->toBe('https://remote.social/actor/inbox');
    $payload = json_decode($outbox['payload_json'], true);
    expect($payload['type'])->toBe('Accept');
});

test('InboxProcessor extracts external links to archive_queue when enabled', function () {
    $this->db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('webarchive_enabled', '1')")
        ->execute();

    $processor = new InboxProcessor($this->site, $this->db);
    $html = '<p>Check <a href="https://external-site.org/info">this external site</a> and <a href="https://example.com/internal">internal</a>.</p>';

    $processor->extractLinksToArchiveQueue($html);

    $items = $this->db->query("SELECT url FROM archive_queue")->fetchAll(PDO::FETCH_COLUMN);
    expect($items)->toContain('https://external-site.org/info');
    expect($items)->not->toContain('https://example.com/internal');
});
