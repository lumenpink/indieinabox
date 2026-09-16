<?php

declare(strict_types=1);

use Indieinabox\ActivityPub\KeyManager;
use Indieinabox\Core\Database;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_keymgr_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $this->db = Database::getDb();

    $this->db->exec("CREATE TABLE IF NOT EXISTS activitypub_keys (
        key_id TEXT PRIMARY KEY,
        public_key TEXT,
        private_key TEXT,
        created_at INTEGER DEFAULT 0
    )");
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec("rm -rf " . escapeshellarg($this->tempDir));
    }
});

it('generates and stores an RSA key pair when none exists', function () {
    $keyManager = new KeyManager($this->db);
    expect($keyManager->getPublicKey('main-key'))->toBeNull();
    expect($keyManager->getPrivateKey('main-key'))->toBeNull();

    $keyManager->ensureKeys('main-key');

    $pub = $keyManager->getPublicKey('main-key');
    $priv = $keyManager->getPrivateKey('main-key');

    expect($pub)->not->toBeNull();
    expect($pub)->toContain('-----BEGIN PUBLIC KEY-----');
    expect($priv)->not->toBeNull();
    expect($priv)->toContain('-----BEGIN PRIVATE KEY-----');
});

it('does not overwrite existing keys on subsequent ensureKeys calls', function () {
    $keyManager = new KeyManager($this->db);
    $this->db->exec("INSERT INTO activitypub_keys (key_id, public_key, private_key, created_at) VALUES ('main-key', 'mock_pub', 'mock_priv', 12345)");

    $keyManager->ensureKeys('main-key');

    expect($keyManager->getPublicKey('main-key'))->toBe('mock_pub');
    expect($keyManager->getPrivateKey('main-key'))->toBe('mock_priv');
});

it('returns null for non-existent key IDs', function () {
    $keyManager = new KeyManager($this->db);
    expect($keyManager->getPublicKey('non-existent'))->toBeNull();
    expect($keyManager->getPrivateKey('non-existent'))->toBeNull();
});
