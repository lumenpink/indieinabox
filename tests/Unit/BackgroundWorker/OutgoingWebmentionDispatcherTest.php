<?php

declare(strict_types=1);

use Indieinabox\BackgroundWorker\OutgoingWebmentionDispatcher;
use Indieinabox\Site;
use Indieinabox\Database;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/wm_outgoing_test_' . uniqid('', true);
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

test('OutgoingWebmentionDispatcher handles empty queue', function () {
    $dispatcher = new OutgoingWebmentionDispatcher($this->site, $this->db);
    ob_start();
    $dispatcher->process();
    $output = ob_get_clean();

    expect($output)->toContain('No outgoing webmentions pending.');
});

test('OutgoingWebmentionDispatcher prunes old records older than 7 days', function () {
    $oldTime = time() - (8 * 86400);
    $this->db->prepare("INSERT INTO outgoing_webmentions (source_url, target_url, status, created_at) VALUES (?, ?, 'sent', ?)")
        ->execute(['https://mysite.com/1', 'https://remote.com/1', $oldTime]);

    $dispatcher = new OutgoingWebmentionDispatcher($this->site, $this->db);
    ob_start();
    $dispatcher->process();
    ob_get_clean();

    $count = $this->db->query("SELECT COUNT(*) FROM outgoing_webmentions")->fetchColumn();
    expect((int)$count)->toBe(0);
});

test('OutgoingWebmentionDispatcher marks as failed when no endpoint discovered', function () {
    $this->db->prepare("INSERT INTO outgoing_webmentions (source_url, target_url, status, created_at) VALUES (?, ?, 'pending', ?)")
        ->execute(['https://mysite.com/post', 'https://127.0.0.1:9999/non-existent-target', time()]);

    $dispatcher = new OutgoingWebmentionDispatcher($this->site, $this->db);
    ob_start();
    $dispatcher->process();
    $output = ob_get_clean();

    expect($output)->toContain('Sending webmention from https://mysite.com/post to https://127.0.0.1:9999/non-existent-target');

    $row = $this->db->query("SELECT status FROM outgoing_webmentions LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    expect($row['status'])->toBe('failed');
});
