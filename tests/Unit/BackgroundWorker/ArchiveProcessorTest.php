<?php

declare(strict_types=1);

use Indieinabox\BackgroundWorker\ArchiveProcessor;
use Indieinabox\Site;
use Indieinabox\Database;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/archive_proc_test_' . uniqid('', true);
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

test('ArchiveProcessor exits cleanly on empty queue', function () {
    $processor = new ArchiveProcessor($this->site, $this->db);
    ob_start();
    $processor->process();
    $output = ob_get_clean();

    expect($output)->toContain('No archive items.');
});

test('ArchiveProcessor processes pending item and triggers callbacks', function () {
    $urlResolver = fn(string $url): string => $url . '/canonical';
    $archiveOrgSender = function (string $url) use (&$calledArchiveOrg) {
        $calledArchiveOrg[] = $url;
    };
    $pdfFetcher = function (string $url, string $normUrl, string $pdfDir) use (&$calledMicrolink): ?string {
        $calledMicrolink[] = $url;
        return '/data/archives/test.pdf';
    };

    $this->db->prepare("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES (?, ?, 0, 'pending')")
        ->execute(['https://example.org/article', time()]);

    $processor = new ArchiveProcessor($this->site, $this->db, $urlResolver, $archiveOrgSender, $pdfFetcher);
    ob_start();
    $processor->process();
    $output = ob_get_clean();

    expect($output)->toContain('Archiving URL: https://example.org/article');
    expect($output)->toContain('Archived: https://example.org/article');
    expect($calledArchiveOrg)->toContain('https://example.org/article/canonical');
    expect($calledMicrolink)->toContain('https://example.org/article/canonical');

    // Queue item deleted upon completion
    $qRows = $this->db->query("SELECT * FROM archive_queue")->fetchAll(PDO::FETCH_ASSOC);
    expect($qRows)->toBeEmpty();

    // archived_links table updated
    $aRow = $this->db->query("SELECT url, local_pdf_path FROM archived_links LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    expect($aRow['url'])->toBe('https://example.org/article/canonical');
    expect($aRow['local_pdf_path'])->toBe('/data/archives/test.pdf');
});

test('ArchiveProcessor skips archiving if archived within 24h without force flag', function () {
    $calledArchiveOrg = [];
    $archiveOrgSender = function (string $url) use (&$calledArchiveOrg) {
        $calledArchiveOrg[] = $url;
    };

    // Seed archived_links
    $this->db->prepare("INSERT INTO archived_links (url, timestamp, local_pdf_path, archive_org_url) VALUES (?, ?, null, null)")
        ->execute(['https://example.org/cached', time()]);

    // Insert to queue with force_archive = 0
    $this->db->prepare("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES (?, ?, 0, 'pending')")
        ->execute(['https://example.org/cached', time()]);

    $processor = new ArchiveProcessor($this->site, $this->db, null, $archiveOrgSender);
    ob_start();
    $processor->process();
    $output = ob_get_clean();

    expect($output)->toContain('Archiving URL: https://example.org/cached');
    expect($calledArchiveOrg)->toBeEmpty();

    $qRows = $this->db->query("SELECT * FROM archive_queue")->fetchAll(PDO::FETCH_ASSOC);
    expect($qRows)->toBeEmpty();
});
