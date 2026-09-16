<?php

declare(strict_types=1);

use Indieinabox\Console\ConsoleKernel;
use Indieinabox\Core\Database;
use Indieinabox\Site;
use Indieinabox\Site\Metadata;

beforeEach(function () {
    $this->site = new Site();
    $this->site->metadata = new Metadata();
    $this->site->metadata->fqdn = 'http://localhost';

    $this->tempDir = sys_get_temp_dir() . '/iiab_integration_tests_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content');
    mkdir($this->tempDir . '/public');

    $this->site->paths->contentDir = $this->tempDir . '/content';
    $this->site->paths->outputDirHtml = $this->tempDir . '/public';
    $this->site->paths->outputDirGemini = $this->tempDir . '/public_gemini';
    $this->site->paths->outputDirGopher = $this->tempDir . '/public_gopher';
    $this->site->paths->outputDirMedia = $this->tempDir . '/public_media';

    $this->kernel = new ConsoleKernel($this->site);

    Database::$dataDir = $this->tempDir;

    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
});

afterEach(function () {
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('ConsoleKernel dispatches post create and generates markdown note with media', function () {
    $mediaSrc = $this->tempDir . '/test_video.mp4';
    file_put_contents($mediaSrc, 'dummy video');

    ob_start();
    try {
        $exitCode = $this->kernel->handle(['indieinabox.php', 'post', 'create', '--text', 'Hello World', '--media', $mediaSrc]);
    } catch (\Throwable $e) {
        $exitCode = 1;
    }
    ob_get_clean();

    expect($exitCode)->toBe(0);

    $contentDir = $this->tempDir . '/../content';
    $notesDir = $contentDir . '/notes';

    expect(is_dir($notesDir))->toBeTrue();

    $files = glob($notesDir . '/*.md');
    expect(count($files))->toBeGreaterThan(0);

    $content = file_get_contents($files[0]);
    expect($content)->toContain('Hello World');
    expect($content)->toContain('<video src="/media/');
});
