<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Indieinabox\Console\Commands\ExportCommand;
use Indieinabox\Console\Commands\ImportCommand;
use Indieinabox\Console\ConsoleKernel;
use Indieinabox\Core\Database;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_cmd_export_test_' . bin2hex(random_bytes(6));
    @mkdir($this->tempDir . '/data', 0755, true);
    @mkdir($this->tempDir . '/content/notes', 0755, true);
    @mkdir($this->tempDir . '/public_media', 0755, true);

    file_put_contents($this->tempDir . '/content/notes/hello.md', '# Hello World');
    file_put_contents($this->tempDir . '/public_media/test.txt', 'media file');

    Database::$dataDir = $this->tempDir . '/data';
    $dbPath = $this->tempDir . '/data/database.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec('CREATE TABLE IF NOT EXISTS sample_table (id INTEGER PRIMARY KEY, note TEXT)');
    Database::getDb()->exec("INSERT INTO sample_table (note) VALUES ('Initial CLI Note')");

    $this->paths = new Paths($this->tempDir);
    $this->site = new Site(null, $this->paths);
    $this->site->metadata->sitename = 'CLI Test Site';
});

afterEach(function () {
    Database::disconnect();
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('ConsoleKernel registers export and import commands and their aliases', function () {
    $kernel = new ConsoleKernel($this->site);

    expect($kernel->getCommand('export'))->toBeInstanceOf(ExportCommand::class);
    expect($kernel->getCommand('backup:export'))->toBeInstanceOf(ExportCommand::class);
    expect($kernel->getCommand('import'))->toBeInstanceOf(ImportCommand::class);
    expect($kernel->getCommand('backup:import'))->toBeInstanceOf(ImportCommand::class);
});

test('ExportCommand creates portable export archive successfully', function () {
    $cmd = new ExportCommand($this->site);
    $targetArchive = $this->tempDir . '/cli_export.tar.gz';

    ob_start();
    $code = $cmd->execute(['indieinabox.php', 'export', $targetArchive]);
    $output = ob_get_clean();

    expect($code)->toBe(0);
    expect($output)->toContain('Generating portable export bundle...')
                   ->toContain('Export created successfully!')
                   ->toContain('cli_export.tar.gz');
    expect(file_exists($targetArchive))->toBeTrue();
});

test('ImportCommand inspects bundle and displays manifest details', function () {
    $exportCmd = new ExportCommand($this->site);
    $targetArchive = $this->tempDir . '/bundle_to_inspect.tar.gz';
    $exportCmd->execute(['indieinabox.php', 'export', $targetArchive]);

    $importCmd = new ImportCommand($this->site);

    ob_start();
    $code = $importCmd->execute(['indieinabox.php', 'import', $targetArchive, '--inspect']);
    $output = ob_get_clean();

    expect($code)->toBe(0);
    expect($output)->toContain('Inspecting portable export bundle:')
                   ->toContain('Archive is VALID!')
                   ->toContain('Generator: indieinabox')
                   ->toContain('CLI Test Site');
});

test('ImportCommand restores data and creates safety backup', function () {
    $exportCmd = new ExportCommand($this->site);
    $targetArchive = $this->tempDir . '/bundle_to_restore.tar.gz';
    $exportCmd->execute(['indieinabox.php', 'export', $targetArchive]);

    // Alter live database
    Database::getDb()->exec("UPDATE sample_table SET note = 'Altered Note' WHERE id = 1");
    file_put_contents($this->tempDir . '/content/notes/hello.md', '# Modified Note');

    $importCmd = new ImportCommand($this->site);

    ob_start();
    $code = $importCmd->execute(['indieinabox.php', 'import', $targetArchive, '--no-build']);
    $output = ob_get_clean();

    expect($code)->toBe(0);
    expect($output)->toContain('Importing portable export bundle:')
                   ->toContain('Import completed successfully!')
                   ->toContain('Safety backup created:');

    // Confirm DB was restored
    $stmt = Database::getDb()->query('SELECT note FROM sample_table WHERE id = 1');
    expect($stmt->fetchColumn())->toBe('Initial CLI Note');

    // Confirm file was restored
    expect(file_get_contents($this->tempDir . '/content/notes/hello.md'))->toBe('# Hello World');
});
