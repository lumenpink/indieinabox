<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Indieinabox\Database;
use Indieinabox\Http\Controllers\ActivityPubController;
use Indieinabox\Site;
use Indieinabox\Site\Metadata;
use PDO;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_ap_ctrl_unit_' . uniqid();
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
    Database::saveSetting('activitypub_avatar', '/avatar.png');
    Database::saveSetting('activitypub_background', '/bg.png');

    $this->controller = new ActivityPubController($this->site, $this->db);
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec("rm -rf " . escapeshellarg($this->tempDir));
    }
});

it('initializes keys automatically during construction', function () {
    $stmt = $this->db->query("SELECT * FROM activitypub_keys WHERE key_id = 'main-key'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    expect($row)->not->toBeFalse();
    expect($row['public_key'])->toContain('-----BEGIN PUBLIC KEY-----');
});

it('handles WebFinger query matching configured handle', function () {
    $_GET['resource'] = 'acct:alice@example.com';

    ob_start();
    $this->controller->webfinger();
    $output = ob_get_clean();

    expect($output)->toBeJson();
    $data = json_decode((string) $output, true);
    expect($data['subject'])->toBe('acct:alice@example.com');
    expect($data['links'][0]['href'])->toBe('https://example.com/actor');
});

it('returns 404 for non-matching WebFinger query', function () {
    $_GET['resource'] = 'acct:bob@example.com';

    ob_start();
    $this->controller->webfinger();
    $output = ob_get_clean();

    $data = json_decode((string) $output, true);
    expect($data['error'])->toBe('not found')
        ->and(http_response_code())->toBe(404);
});

it('handles Actor request returning ActivityStreams JSON-LD with avatar and background', function () {
    ob_start();
    $this->controller->actor();
    $output = ob_get_clean();

    expect($output)->toBeJson();
    $data = json_decode((string) $output, true);

    expect($data['id'])->toBe('https://example.com/actor');
    expect($data['type'])->toBe('Person');
    expect($data['preferredUsername'])->toBe('alice');
    expect($data['name'])->toBe('Example Site');
    expect($data['summary'])->toBe('Alice bio');
    expect($data['inbox'])->toBe('https://example.com/inbox');
    expect($data['outbox'])->toBe('https://example.com/outbox');
    expect($data['publicKey']['id'])->toBe('https://example.com/actor#main-key');
    expect($data['icon']['url'])->toBe('https://example.com/avatar.png');
    expect($data['image']['url'])->toBe('https://example.com/bg.png');
});

it('handles Inbox POST and enqueues into inbox_queue', function () {
    $_SERVER['REQUEST_URI'] = '/inbox';
    $_SERVER['REQUEST_METHOD'] = 'POST';

    ob_start();
    $this->controller->inbox();
    ob_get_clean();

    $stmt = $this->db->query("SELECT * FROM inbox_queue");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    expect(count($rows))->toBe(1);
    expect($rows[0]['type'])->toBe('activitypub');
    $payload = json_decode((string) $rows[0]['payload_json'], true);
    expect($payload['method'])->toBe('POST');
    expect($payload['path'])->toBe('/inbox');
});

it('handles Outbox request returning empty OrderedCollection', function () {
    ob_start();
    $this->controller->outbox();
    $output = ob_get_clean();

    expect($output)->toBeJson();
    $data = json_decode((string) $output, true);
    expect($data['id'])->toBe('https://example.com/outbox');
    expect($data['type'])->toBe('OrderedCollection');
    expect($data['totalItems'])->toBe(0);
});
