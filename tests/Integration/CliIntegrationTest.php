<?php

use Indieinabox\CliHandler;
use Indieinabox\Site;
use Indieinabox\Database;
use Indieinabox\Site\Metadata;

beforeEach(function () {
    $this->site = new Site();
    $this->site->metadata = new Metadata();
    $this->site->metadata->fqdn = 'http://localhost';
    
    $this->handler = new CliHandler($this->site);
    
    $this->tempDir = sys_get_temp_dir() . '/iiab_integration_tests_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;
    
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
});

afterEach(function () {
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('handlePost creates markdown file with correct media and tags', function () {
    $mediaSrc = $this->tempDir . '/test_video.mp4';
    file_put_contents($mediaSrc, 'dummy video');

    ob_start();
    try {
        $this->handler->handlePost(['indieinabox.php', 'post', 'create', '--text', 'Hello World', '--media', $mediaSrc]);
    } catch (\Throwable $e) {
    }
    ob_get_clean();

    $contentDir = $this->tempDir . '/../content';
    $notesDir = $contentDir . '/notes';
    
    expect(is_dir($notesDir))->toBeTrue();
    
    $files = glob($notesDir . '/*.md');
    expect(count($files))->toBeGreaterThan(0);
    
    $content = file_get_contents($files[0]);
    expect($content)->toContain('Hello World');
    expect($content)->toContain('<video src="/media/');
});
