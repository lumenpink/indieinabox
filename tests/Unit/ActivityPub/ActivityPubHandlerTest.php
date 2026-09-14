<?php

declare(strict_types=1);

use Indieinabox\ActivityPubHandler;
use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\ActivityPub\KeyManager;
use Indieinabox\Site;
use Indieinabox\Site\Metadata;
use Indieinabox\Database;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_ap_handler_unit_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $this->site = new Site();
    $this->site->metadata = new Metadata();
    $this->site->metadata->fqdn = 'https://example.com';
    $this->site->metadata->title = 'Example Site';

    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $this->db = Database::getDb();

    $this->db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    $this->db->exec("CREATE TABLE IF NOT EXISTS activitypub_keys (key_id TEXT PRIMARY KEY, public_key TEXT, private_key TEXT, created_at INTEGER DEFAULT 0)");
    $this->db->exec("CREATE TABLE IF NOT EXISTS activitypub_followers (actor_url TEXT PRIMARY KEY, inbox_url TEXT, shared_inbox_url TEXT)");
    $this->db->exec("CREATE TABLE IF NOT EXISTS activitypub_outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, payload_json TEXT, target_inbox TEXT, status TEXT, created_at INTEGER)");
    $this->db->exec("CREATE TABLE IF NOT EXISTS inbox_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, payload_json TEXT, created_at INTEGER)");

    Database::saveSetting('activitypub_handle', 'alice');
    Database::saveSetting('activitypub_bio', 'Alice bio');
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec("rm -rf " . escapeshellarg($this->tempDir));
    }
});

it('initializes keys automatically during construction', function () {
    $handler = new ActivityPubHandler($this->site, $this->db);

    $stmt = $this->db->query("SELECT * FROM activitypub_keys WHERE key_id = 'main-key'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    expect($row)->not->toBeFalse();
    expect($row['public_key'])->toContain('-----BEGIN PUBLIC KEY-----');
});

it('handles WebFinger query matching configured handle', function () {
    $handler = new ActivityPubHandler($this->site, $this->db);

    $_GET['resource'] = 'acct:alice@example.com';

    ob_start();
    $handler->handleWebFinger();
    $output = ob_get_clean();

    expect($output)->toBeJson();
    $data = json_decode($output, true);
    expect($data['subject'])->toBe('acct:alice@example.com');
    expect($data['links'][0]['href'])->toBe('https://example.com/actor');
});

it('returns 404 for non-matching WebFinger query', function () {
    $handler = new ActivityPubHandler($this->site, $this->db);

    $_GET['resource'] = 'acct:bob@example.com';

    ob_start();
    $handler->handleWebFinger();
    $output = ob_get_clean();

    $data = json_decode($output, true);
    expect($data['error'])->toBe('not found');
});

it('handles Actor request returning ActivityStreams JSON-LD', function () {
    $handler = new ActivityPubHandler($this->site, $this->db);

    ob_start();
    $handler->handleActor();
    $output = ob_get_clean();

    expect($output)->toBeJson();
    $data = json_decode($output, true);

    expect($data['id'])->toBe('https://example.com/actor');
    expect($data['type'])->toBe('Person');
    expect($data['preferredUsername'])->toBe('alice');
    expect($data['name'])->toBe('Example Site');
    expect($data['summary'])->toBe('Alice bio');
    expect($data['inbox'])->toBe('https://example.com/inbox');
    expect($data['outbox'])->toBe('https://example.com/outbox');
    expect($data['publicKey']['id'])->toBe('https://example.com/actor#main-key');
});

it('handles Inbox POST and enqueues into inbox_queue', function () {
    $handler = new ActivityPubHandler($this->site, $this->db);

    $_SERVER['REQUEST_URI'] = '/inbox';
    $_SERVER['REQUEST_METHOD'] = 'POST';

    ob_start();
    $handler->handleInbox();
    ob_get_clean();

    $stmt = $this->db->query("SELECT * FROM inbox_queue");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    expect(count($rows))->toBe(1);
    expect($rows[0]['type'])->toBe('activitypub');
    $payload = json_decode($rows[0]['payload_json'], true);
    expect($payload['method'])->toBe('POST');
    expect($payload['path'])->toBe('/inbox');
});

it('handles Outbox request returning empty OrderedCollection', function () {
    $handler = new ActivityPubHandler($this->site, $this->db);

    ob_start();
    $handler->handleOutbox();
    $output = ob_get_clean();

    expect($output)->toBeJson();
    $data = json_decode($output, true);
    expect($data['id'])->toBe('https://example.com/outbox');
    expect($data['type'])->toBe('OrderedCollection');
    expect($data['totalItems'])->toBe(0);
});

it('queues an Accept activity into outbox', function () {
    $handler = new ActivityPubHandler($this->site, $this->db);

    $follow = [
        'id' => 'https://mastodon.social/follow/1',
        'type' => 'Follow',
        'actor' => 'https://mastodon.social/@bob',
        'object' => 'https://example.com/actor'
    ];

    $handler->queueAcceptFollow($follow, 'https://mastodon.social/@bob/inbox');

    $stmt = $this->db->query("SELECT * FROM activitypub_outbox");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    expect(count($rows))->toBe(1);
    expect($rows[0]['target_inbox'])->toBe('https://mastodon.social/@bob/inbox');
    $payload = json_decode($rows[0]['payload_json'], true);
    expect($payload['type'])->toBe('Accept');
    expect($payload['object'])->toBe($follow);
});

it('queues a Create activity and broadcasts to all distinct follower inboxes', function () {
    $handler = new ActivityPubHandler($this->site, $this->db);

    $this->db->exec("INSERT INTO activitypub_followers (actor_url, inbox_url, shared_inbox_url) VALUES
        ('https://mastodon.social/@bob', 'https://mastodon.social/@bob/inbox', 'https://mastodon.social/inbox'),
        ('https://mastodon.social/@charlie', 'https://mastodon.social/@charlie/inbox', 'https://mastodon.social/inbox'),
        ('https://pixelfed.social/@dora', 'https://pixelfed.social/@dora/inbox', '')
    ");

    $handler->queueCreateActivity('https://example.com/post/hello', '<p>Hello!</p>', 'Hello');

    $stmt = $this->db->query("SELECT target_inbox FROM activitypub_outbox ORDER BY target_inbox ASC");
    $targets = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Shared inbox deduplication: 'https://mastodon.social/inbox' appears once, pixelfed appears once
    expect(count($targets))->toBe(2);
    expect($targets)->toContain('https://mastodon.social/inbox');
    expect($targets)->toContain('https://pixelfed.social/@dora/inbox');
});

it('delegates buildObjectForPageArray to ActivityBuilder statically', function () {
    $res = ActivityPubHandler::buildObjectForPageArray(
        'https://example.com/item/1',
        'https://example.com/actor',
        'https://example.com',
        'Text',
        null
    );

    expect($res['type'])->toBe('Note');
    expect($res['content'])->toBe('Text');
});
