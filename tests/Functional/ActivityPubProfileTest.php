<?php

use Indieinabox\ActivityPubHandler;
use Indieinabox\Site;
use Indieinabox\Database;
use Indieinabox\Site\Metadata;

/**
 * @property Site $site
 * @property string $tempDir
 */

beforeEach(function () {
    Database::disconnect();
    $this->site = new Site();
    $this->site->metadata = new Metadata();
    $this->site->metadata->fqdn = 'http://localhost';
    $this->site->metadata->defaultTitle = 'Test Site';
    $this->site->metadata->sitename = 'Test Sitename';
    
    $this->tempDir = sys_get_temp_dir() . '/iiab_functional_tests_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;
    
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_keys (key_id TEXT PRIMARY KEY, public_key TEXT, private_key TEXT, created_at INTEGER DEFAULT 0)");
    
    Database::saveSetting('activitypub_handle', 'test_user');
    Database::saveSetting('activitypub_bio', 'This is a test bio from DB.');
    Database::saveSetting('activitypub_avatar', '/media/avatar.png');
    Database::saveSetting('activitypub_background', '/media/background.png');
    
    Database::getDb()->exec("INSERT INTO activitypub_keys (key_id, public_key, private_key, created_at) VALUES ('main-key', 'mock_public_key', '', 0)");
});

afterEach(function () {
    Database::disconnect();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('handleActor outputs correctly formatted ActivityPub JSON-LD with bio and media', function () {
    $handler = new ActivityPubHandler($this->site);
    
    ob_start();
    $handler->handleActor();
    $output = ob_get_clean();
    
    $data = json_decode($output, true);
    
    expect($data)->not->toBeNull();
    expect($data['preferredUsername'])->toBe('test_user');
    expect($data['summary'])->toBe('This is a test bio from DB.');
    expect($data['icon']['url'])->toBe('http://localhost/media/avatar.png');
    expect($data['image']['url'])->toBe('http://localhost/media/background.png');
    expect($data['publicKey']['publicKeyPem'])->toBe('mock_public_key');
});
