<?php

declare(strict_types=1);

use Indieinabox\Services\ArchiveService;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/archive_service_test_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);

    $this->db = new PDO('sqlite:' . $this->tempDir . '/test.sqlite');
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Schema matching production migration M20260921000005CreateArchiveAndQueueTables
    $this->db->exec('CREATE TABLE archive_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        url TEXT NOT NULL,
        status TEXT DEFAULT "pending",
        requested_at INTEGER NOT NULL,
        force_archive INTEGER DEFAULT 0
    )');

    $this->db->exec('CREATE TABLE archived_links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        url TEXT NOT NULL,
        timestamp INTEGER NOT NULL,
        local_pdf_path TEXT,
        archive_org_url TEXT
    )');

    $this->db->exec('CREATE TABLE archive_aliases (
        original_url TEXT PRIMARY KEY,
        final_url TEXT NOT NULL
    )');
});

afterEach(function () {
    $this->db = null;
    if (is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

it('resolves alias correctly against original_url and final_url columns in archive_aliases', function () {
    $this->db->prepare('INSERT INTO archive_aliases (original_url, final_url) VALUES (?, ?)')
        ->execute(['https://old.link/post', 'https://canonical.link/post']);

    $service = new ArchiveService($this->db);
    $resolved = $service->resolveAlias('https://old.link/post');

    expect($resolved)->toBe('https://canonical.link/post');
});

it('returns original url if alias is not found in archive_aliases', function () {
    $service = new ArchiveService($this->db);
    $resolved = $service->resolveAlias('https://non-aliased.link/post');

    expect($resolved)->toBe('https://non-aliased.link/post');
});

it('finds snapshot without throwing PDOException on real schema', function () {
    $now = time();
    $this->db->prepare('INSERT INTO archived_links (url, timestamp, local_pdf_path, archive_org_url) VALUES (?, ?, ?, ?)')
        ->execute(['https://example.com/test', $now, '/data/test.pdf', 'https://web.archive.org/web/test']);

    $service = new ArchiveService($this->db);
    $snapshot = $service->findSnapshot('https://example.com/test');

    expect($snapshot)->not->toBeNull();
    expect($snapshot['archive_org_url'])->toBe('https://web.archive.org/web/test');
});

it('gracefully falls back when database encounters an error', function () {
    $brokenDb = new PDO('sqlite::memory:');
    // brokenDb does not have tables created
    $service = new ArchiveService($brokenDb);

    expect($service->resolveAlias('https://fallback.test'))->toBe('https://fallback.test');
    expect($service->findSnapshot('https://fallback.test'))->toBeNull();
});

it('queues url for force archive with resolved alias', function () {
    $this->db->prepare('INSERT INTO archive_aliases (original_url, final_url) VALUES (?, ?)')
        ->execute(['https://short.url/x', 'https://destination.url/x']);

    $service = new ArchiveService($this->db);
    $result = $service->queueForceArchive('https://short.url/x');

    expect($result)->toBeTrue();
    $queued = $this->db->query("SELECT * FROM archive_queue WHERE url = 'https://destination.url/x'")->fetch(PDO::FETCH_ASSOC);
    expect($queued)->not->toBeFalse();
    expect((int)$queued['force_archive'])->toBe(1);
});
