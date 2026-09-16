<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Webmention\WebmentionSender;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_wmsender_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $this->db = Database::getDb();

    $this->db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    $this->db->exec("CREATE TABLE IF NOT EXISTS outgoing_webmentions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source_url TEXT,
        target_url TEXT,
        created_at INTEGER
    )");
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec("rm -rf " . escapeshellarg($this->tempDir));
    }
});

it('does not queue webmentions when webmention_enabled is not set', function () {
    Database::saveSetting('webmention_enabled', '');

    WebmentionSender::queueOutgoingWebmentions(
        'https://myblog.com/post/1',
        ['in-reply-to' => 'https://remote.com/note/1'],
        'Check out https://another.com/story',
        $this->db
    );

    $stmt = $this->db->query("SELECT * FROM outgoing_webmentions");
    expect($stmt->fetchAll(PDO::FETCH_ASSOC))->toHaveCount(0);
});

it('queues outgoing webmentions and avoids duplicate entries', function () {
    Database::saveSetting('webmention_enabled', '1');

    WebmentionSender::queueOutgoingWebmentions(
        'https://myblog.com/post/1',
        ['in-reply-to' => 'https://remote.com/note/1'],
        'Check out https://another.com/story and again https://remote.com/note/1',
        $this->db
    );

    $stmt = $this->db->query("SELECT * FROM outgoing_webmentions ORDER BY target_url ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    expect($rows)->toHaveCount(2);
    expect($rows[0]['source_url'])->toBe('https://myblog.com/post/1');
    expect($rows[0]['target_url'])->toBe('https://another.com/story');
    expect($rows[1]['target_url'])->toBe('https://remote.com/note/1');

    // Calling again does not insert duplicates
    WebmentionSender::queueOutgoingWebmentions(
        'https://myblog.com/post/1',
        ['in-reply-to' => 'https://remote.com/note/1'],
        'Check out https://another.com/story',
        $this->db
    );

    $stmt2 = $this->db->query("SELECT * FROM outgoing_webmentions");
    expect($stmt2->fetchAll(PDO::FETCH_ASSOC))->toHaveCount(2);
});
