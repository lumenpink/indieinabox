<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Core\Database;
use Indieinabox\Services\ExportService;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use PharData;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/export_test_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);

    // Setup mock data dir with sqlite DB
    $dataDir = $this->tempDir . '/data';
    mkdir($dataDir, 0755, true);
    Database::$dataDir = $dataDir;
    $dbFile = $dataDir . '/.indieinabox.sqlite';
    $db = new \PDO('sqlite:' . $dbFile);
    $db->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, val TEXT); INSERT INTO test VALUES (1, "hello");');

    // Setup mock content dir
    $contentDir = $this->tempDir . '/content';
    mkdir($contentDir, 0755, true);
    file_put_contents($contentDir . '/post.md', '# Test Post');

    // Setup mock public_media dir
    $mediaDir = $this->tempDir . '/public_media';
    mkdir($mediaDir, 0755, true);
    file_put_contents($mediaDir . '/avatar.png', 'fake_png_data');

    $paths = new Paths($this->tempDir);
    $this->site = new Site(null, $paths);
    $this->site->metadata->sitename = 'Export Test Blog';
    $this->site->metadata->fqdn = 'https://export.example';
});

afterEach(function () {
    Database::$dataDir = '';
    if (is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('ExportService exports database, content, media, and manifest into portable archive', function () {
    $service = new ExportService($this->site);
    $outFile = $this->tempDir . '/output.tar.gz';

    $result = $service->export($outFile);

    expect($result['success'])->toBeTrue()
        ->and(file_exists($outFile))->toBeTrue()
        ->and($result['size_bytes'])->toBeGreaterThan(0)
        ->and($result['manifest']['format'])->toBe('indieinabox_portable_export')
        ->and($result['manifest']['sitename'])->toBe('Export Test Blog')
        ->and($result['manifest']['fqdn'])->toBe('https://export.example')
        ->and($result['manifest']['files'])->toHaveKeys([
            'database/indieinabox.sqlite',
            'content/post.md',
            'public_media/avatar.png',
        ]);

    // Inspect archive contents using PharData
    $phar = new PharData($outFile);
    expect($phar->offsetExists('manifest.json'))->toBeTrue()
        ->and($phar->offsetExists('database/indieinabox.sqlite'))->toBeTrue()
        ->and($phar->offsetExists('content/post.md'))->toBeTrue()
        ->and($phar->offsetExists('public_media/avatar.png'))->toBeTrue();
});

test('ExportService respects skip_media and skip_content flags', function () {
    $service = new ExportService($this->site);
    $outFile = $this->tempDir . '/minimal.tar.gz';

    $result = $service->export($outFile, [
        'skip_media' => true,
        'skip_content' => true,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['manifest']['files'])->toHaveKey('database/indieinabox.sqlite')
        ->and($result['manifest']['files'])->not->toHaveKey('content/post.md')
        ->and($result['manifest']['files'])->not->toHaveKey('public_media/avatar.png');

    $phar = new PharData($outFile);
    expect($phar->offsetExists('manifest.json'))->toBeTrue()
        ->and($phar->offsetExists('database/indieinabox.sqlite'))->toBeTrue()
        ->and($phar->offsetExists('content/post.md'))->toBeFalse()
        ->and($phar->offsetExists('public_media/avatar.png'))->toBeFalse();
});
