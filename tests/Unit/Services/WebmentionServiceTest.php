<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Core\Database;
use Indieinabox\Services\WebmentionService;
use Indieinabox\Site\Site;
use Indieinabox\Site\Paths;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_wmservice_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/data');
    mkdir($this->tempDir . '/public_html', 0777, true);

    Database::$dataDir = $this->tempDir . '/data';
    $dbPath = Database::$dataDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS inbox_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )");

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);
    $this->site->metadata->fqdn = 'https://wmsite.example';
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    \Indieinabox\Core\Container::getInstance()->flush();
    \Indieinabox\Support\FileUtils::recursiveRmdir($this->tempDir);
});

test('WebmentionService queues incoming mentions in inbox_queue', function () {
    $service = new WebmentionService(Database::getDb());

    $queued = $service->queue('https://other.com/post', 'https://wmsite.example/notes/1');
    expect($queued)->toBeTrue();

    $stmt = Database::getDb()->query("SELECT type, payload_json FROM inbox_queue WHERE type = 'webmention'");
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    expect($row)->not->toBeFalse();

    $payload = json_decode($row['payload_json'], true);
    expect($payload['source'])->toBe('https://other.com/post');
    expect($payload['target'])->toBe('https://wmsite.example/notes/1');
});

test('WebmentionService validates whether target belongs to site and exists on disk', function () {
    $service = new WebmentionService(Database::getDb());

    // Target from different domain
    expect($service->isValidTarget('https://external.org/hello', $this->site))->toBeFalse();

    // Target on site but file does not exist yet
    expect($service->isValidTarget('https://wmsite.example/articles/first-post', $this->site))->toBeFalse();

    // Create target file on disk
    mkdir($this->tempDir . '/public_html/articles/first-post', 0777, true);
    file_put_contents($this->tempDir . '/public_html/articles/first-post/index.html', '<h1>Hello</h1>');

    expect($service->isValidTarget('https://wmsite.example/articles/first-post', $this->site))->toBeTrue();
});

test('WebmentionService stores and retrieves mentions per slug with deduplication', function () {
    $service = new WebmentionService(Database::getDb());

    expect($service->getMentions('articles/my-post'))->toBeEmpty();

    $mention1 = [
        'source' => 'https://author1.example/reply',
        'type' => 'reply',
        'content' => 'Great article!',
    ];
    $service->saveMention('articles/my-post', $mention1);

    $mentions = $service->getMentions('articles/my-post');
    expect($mentions)->toHaveCount(1);
    expect($mentions[0]['content'])->toBe('Great article!');

    // Update from same source (deduplicate)
    $mention1Updated = [
        'source' => 'https://author1.example/reply',
        'type' => 'reply',
        'content' => 'Great article! (Updated)',
    ];
    $service->saveMention('articles/my-post', $mention1Updated);

    $mentionsUpdated = $service->getMentions('articles/my-post');
    expect($mentionsUpdated)->toHaveCount(1);
    expect($mentionsUpdated[0]['content'])->toBe('Great article! (Updated)');
});
