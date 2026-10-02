<?php

declare(strict_types=1);

use Indieinabox\BackgroundWorker\ArchiveProcessor;
use Indieinabox\Core\Database;
use Indieinabox\Site\Site;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/archive_sub_test_' . uniqid('', true);
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

it('successfully processes Archive.org SPN v1 submission (HTTP 200) and deletes from queue', function () {
    $archiveOrgSender = function (string $url) {
        return [
            'success' => true,
            'status_code' => 200,
            'archive_url' => 'https://web.archive.org/web/20261002000000/' . $url,
            'job_id' => null,
        ];
    };

    $pdfFetcher = function () {
        return '/data/archives/spn1.pdf';
    };

    $this->db->prepare("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES (?, ?, 0, 'pending')")
        ->execute(['https://example.com/blog/pos-capitalismo', time()]);

    $processor = new ArchiveProcessor($this->site, $this->db, null, $archiveOrgSender, $pdfFetcher);
    ob_start();
    $processor->process();
    $output = ob_get_clean();

    expect($output)->toContain('Archived: https://example.com/blog/pos-capitalismo');

    // Should be removed from queue
    $queueItems = $this->db->query("SELECT * FROM archive_queue")->fetchAll(PDO::FETCH_ASSOC);
    expect($queueItems)->toBeEmpty();

    // Should be recorded in archived_links
    $archived = $this->db->query("SELECT * FROM archived_links WHERE url = 'https://example.com/blog/pos-capitalismo'")->fetch(PDO::FETCH_ASSOC);
    expect($archived)->not->toBeFalse();
    expect($archived['archive_org_url'])->toBe('https://web.archive.org/web/20261002000000/https://example.com/blog/pos-capitalismo');
    expect($archived['local_pdf_path'])->toBe('/data/archives/spn1.pdf');
});

it('successfully handles Archive.org SPN v2 with job_id and persists metadata', function () {
    $archiveOrgSender = function (string $url) {
        return [
            'success' => true,
            'status_code' => 200,
            'job_id' => 'spn2-20261002-abc123xyz',
            'archive_url' => 'https://web.archive.org/web/20261002120000/' . $url,
        ];
    };

    $this->db->prepare("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES (?, ?, 0, 'pending')")
        ->execute(['https://example.com/spn2-post', time()]);

    $processor = new ArchiveProcessor($this->site, $this->db, null, $archiveOrgSender, fn() => null);
    ob_start();
    $processor->process();
    $output = ob_get_clean();

    expect($output)->toContain('Archived: https://example.com/spn2-post');

    $archived = $this->db->query("SELECT * FROM archived_links WHERE url = 'https://example.com/spn2-post'")->fetch(PDO::FETCH_ASSOC);
    expect($archived)->not->toBeFalse();
    expect($archived['archive_org_url'])->toBe('https://web.archive.org/web/20261002120000/https://example.com/spn2-post');
});

it('preserves item in archive_queue as pending when Archive.org returns HTTP 429 rate limit', function () {
    $archiveOrgSender = function (string $url) {
        return [
            'success' => false,
            'status_code' => 429,
            'error' => 'Rate limit exceeded: You have reached the limit of active capture requests.',
            'job_id' => null,
            'archive_url' => null,
        ];
    };

    $this->db->prepare("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES (?, ?, 0, 'pending')")
        ->execute(['https://example.com/rate-limited-post', time()]);

    $processor = new ArchiveProcessor($this->site, $this->db, null, $archiveOrgSender);
    ob_start();
    $processor->process();
    $output = ob_get_clean();

    expect($output)->toContain('Archive.org dispatch failed (HTTP 429)');
    expect($output)->toContain('Re-queueing for retry.');

    // Item must still exist in archive_queue with status 'pending'
    $queueItems = $this->db->query("SELECT * FROM archive_queue WHERE url = 'https://example.com/rate-limited-post'")->fetchAll(PDO::FETCH_ASSOC);
    expect($queueItems)->toHaveCount(1);
    expect($queueItems[0]['status'])->toBe('pending');

    // Must NOT exist in archived_links
    $archived = $this->db->query("SELECT * FROM archived_links WHERE url = 'https://example.com/rate-limited-post'")->fetch(PDO::FETCH_ASSOC);
    expect($archived)->toBeFalse();
});

it('preserves item in archive_queue when Archive.org returns HTTP 503 transient failure', function () {
    $archiveOrgSender = function (string $url) {
        return [
            'success' => false,
            'status_code' => 503,
            'error' => 'Service Unavailable',
            'job_id' => null,
            'archive_url' => null,
        ];
    };

    $this->db->prepare("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES (?, ?, 0, 'pending')")
        ->execute(['https://example.com/down-post', time()]);

    $processor = new ArchiveProcessor($this->site, $this->db, null, $archiveOrgSender);
    ob_start();
    $processor->process();
    $output = ob_get_clean();

    expect($output)->toContain('Archive.org dispatch failed (HTTP 503)');

    $queueItems = $this->db->query("SELECT * FROM archive_queue WHERE url = 'https://example.com/down-post'")->fetchAll(PDO::FETCH_ASSOC);
    expect($queueItems)->toHaveCount(1);
    expect($queueItems[0]['status'])->toBe('pending');
});

it('records canonical redirect in archive_aliases and submits final URL', function () {
    $urlResolver = fn(string $url) => 'https://example.com/final-location';

    $submittedUrl = null;
    $archiveOrgSender = function (string $url) use (&$submittedUrl) {
        $submittedUrl = $url;
        return ['success' => true, 'status_code' => 200];
    };

    $this->db->prepare("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES (?, ?, 0, 'pending')")
        ->execute(['https://example.com/short-alias', time()]);

    $processor = new ArchiveProcessor($this->site, $this->db, $urlResolver, $archiveOrgSender, fn() => null);
    ob_start();
    $processor->process();
    ob_get_clean();

    expect($submittedUrl)->toBe('https://example.com/final-location');

    // Check alias mapping was persisted
    $alias = $this->db->query("SELECT * FROM archive_aliases WHERE original_url = 'https://example.com/short-alias'")->fetch(PDO::FETCH_ASSOC);
    expect($alias)->not->toBeFalse();
    expect($alias['final_url'])->toBe('https://example.com/final-location');
});
