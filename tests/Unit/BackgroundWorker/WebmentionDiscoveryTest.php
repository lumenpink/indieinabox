<?php

declare(strict_types=1);

use Indieinabox\BackgroundWorker\WebmentionDiscovery;
use Indieinabox\Site\Site;
use Indieinabox\Core\Database;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/wm_discovery_test_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $this->db = Database::getDb();

    $this->site = new Site();
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('WebmentionDiscovery does nothing when queue is empty', function () {
    $discovery = new WebmentionDiscovery($this->site, $this->db);
    ob_start();
    $discovery->process();
    $output = ob_get_clean();

    expect($output)->toContain('No domains to discover.');
});

test('WebmentionDiscovery updates cache with discovery result via link tag', function () {
    $this->db->prepare("INSERT INTO webmention_discovery_cache (domain, supports_webmention, last_checked) VALUES (?, 0, 0)")
        ->execute(['blog.example.com']);

    $fetchMock = function (string $url) {
        return '<html><head><link rel="webmention" href="https://blog.example.com/endpoint"></head><body>Hello</body></html>';
    };

    $discovery = new WebmentionDiscovery($this->site, $this->db, $fetchMock);
    ob_start();
    $discovery->process();
    $output = ob_get_clean();

    expect($output)->toContain('Checking https://blog.example.com/ for webmention support...');

    $stmt = $this->db->prepare("SELECT supports_webmention, last_checked FROM webmention_discovery_cache WHERE domain = ?");
    $stmt->execute(['blog.example.com']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    expect($row['supports_webmention'])->toBe(1);
    expect($row['last_checked'])->toBeGreaterThan(0);
});

test('WebmentionDiscovery marks domain as 0 when webmention link not found', function () {
    $this->db->prepare("INSERT INTO webmention_discovery_cache (domain, supports_webmention, last_checked) VALUES (?, 0, 0)")
        ->execute(['nodiscovery.example.com']);

    $fetchMock = function (string $url) {
        return '<html><head></head><body>No endpoint here</body></html>';
    };

    $discovery = new WebmentionDiscovery($this->site, $this->db, $fetchMock);
    ob_start();
    $discovery->process();
    $output = ob_get_clean();

    $stmt = $this->db->prepare("SELECT supports_webmention, last_checked FROM webmention_discovery_cache WHERE domain = ?");
    $stmt->execute(['nodiscovery.example.com']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    expect($row['supports_webmention'])->toBe(0);
    expect($row['last_checked'])->toBeGreaterThan(0);
});
