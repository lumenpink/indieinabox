<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Core\Database;
use Indieinabox\Services\ExportService;
use Indieinabox\Services\ImportService;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use PDO;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iiab_test_import_' . bin2hex(random_bytes(6));
    @mkdir($this->tempDir . '/content/notes', 0755, true);
    @mkdir($this->tempDir . '/public_media', 0755, true);
    @mkdir($this->tempDir . '/data', 0755, true);

    file_put_contents($this->tempDir . '/content/notes/initial.md', '# Initial Note');
    file_put_contents($this->tempDir . '/public_media/photo.jpg', 'fake-image-bytes');

    $dbPath = $this->tempDir . '/data/database.sqlite';
    Database::$dataDir = $this->tempDir . '/data';
    Database::disconnect();
    Database::connect($dbPath);
    Database::getDb()->exec('CREATE TABLE IF NOT EXISTS test_data (id INTEGER PRIMARY KEY, title TEXT)');
    Database::getDb()->exec("INSERT INTO test_data (title) VALUES ('Original Site Title')");

    $this->paths = new Paths($this->tempDir);
    $this->site = new Site(null, $this->paths);
    $this->site->metadata->sitename = 'Original Site';
    $this->exportService = new ExportService($this->site);
    $this->importService = new ImportService($this->site, $this->exportService);
});

afterEach(function () {
    Database::disconnect();
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($this->tempDir);
    }
});

test('ImportService inspect validates valid export bundle and returns manifest', function () {
    $exportFile = $this->tempDir . '/export_bundle.tar.gz';
    $this->exportService->export($exportFile);

    expect(file_exists($exportFile))->toBeTrue();

    $inspection = $this->importService->inspect($exportFile);

    expect($inspection['valid'])->toBeTrue();
    expect($inspection['errors'])->toBeEmpty();
    expect($inspection['manifest']['generator'])->toBe('indieinabox');
    expect($inspection['manifest']['files'])->toBeArray();
    expect(count($inspection['manifest']['files']))->toBeGreaterThanOrEqual(3);
});

test('ImportService inspect detects missing or invalid files in bundle', function () {
    $exportFile = $this->tempDir . '/export_bundle.tar.gz';
    $this->exportService->export($exportFile);

    // Tamper with archive: create archive with invalid manifest
    $badDir = $this->tempDir . '/bad_staging';
    @mkdir($badDir, 0755, true);
    file_put_contents($badDir . '/manifest.json', json_encode([
        'format_version' => '1.0',
        'files' => [
            ['path' => 'nonexistent.txt', 'sha256' => 'invalidhash']
        ]
    ]));

    $badTar = $this->tempDir . '/bad_bundle.tar';
    $phar = new \PharData($badTar);
    $phar->buildFromDirectory($badDir);
    $badArchive = $this->tempDir . '/bad_bundle.tar.gz';
    $phar->compress(\Phar::GZ);
    unset($phar);

    $inspection = $this->importService->inspect($badArchive);

    expect($inspection['valid'])->toBeFalse();
    expect($inspection['errors'])->not->toBeEmpty();
    expect($inspection['errors'][0])->toContain('Missing bundled file');
});

test('ImportService imports bundle, restores DB and files, and creates safety backup', function () {
    // 1. Export initial state
    $exportFile = $this->tempDir . '/original_export.tar.gz';
    $this->exportService->export($exportFile);

    // 2. Modify live site data
    Database::getDb()->exec("UPDATE test_data SET title = 'Altered Title' WHERE id = 1");
    file_put_contents($this->tempDir . '/content/notes/initial.md', '# Modified Note');

    // 3. Import original export bundle
    $result = $this->importService->import($exportFile, [
        'verify_checksums' => true,
        'safety_backup' => true,
        'rebuild_site' => false,
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['files_restored'])->toBeGreaterThan(0);
    expect($result['safety_backup'])->not->toBeNull();
    expect(file_exists($result['safety_backup']))->toBeTrue();

    // 4. Verify DB was restored
    $stmt = Database::getDb()->query('SELECT title FROM test_data WHERE id = 1');
    $title = $stmt->fetchColumn();
    expect($title)->toBe('Original Site Title');

    // 5. Verify content file was restored
    expect(file_get_contents($this->tempDir . '/content/notes/initial.md'))->toBe('# Initial Note');
});

test('ImportService throws exception if checksum validation fails during import', function () {
    $badDir = $this->tempDir . '/tampered_staging';
    @mkdir($badDir, 0755, true);
    file_put_contents($badDir . '/dummy.txt', 'real content');
    file_put_contents($badDir . '/manifest.json', json_encode([
        'format_version' => '1.0',
        'files' => [
            ['path' => 'dummy.txt', 'sha256' => 'deadbeefcafebabe']
        ]
    ]));

    $badTar = $this->tempDir . '/tampered.tar';
    $phar = new \PharData($badTar);
    $phar->buildFromDirectory($badDir);
    $phar->compress(\Phar::GZ);
    unset($phar);

    $badArchive = $this->tempDir . '/tampered.tar.gz';

    expect(function () use ($badArchive) {
        $this->importService->import($badArchive, ['verify_checksums' => true, 'safety_backup' => false]);
    })->toThrow(\RuntimeException::class);
});
