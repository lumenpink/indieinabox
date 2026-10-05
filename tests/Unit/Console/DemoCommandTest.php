<?php

declare(strict_types=1);

use Indieinabox\Console\Commands\DemoCommand;
use Indieinabox\Console\ConsoleKernel;
use Indieinabox\Core\Database;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_demo_cmd_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/data', 0777, true);
    mkdir($this->tempDir . '/content', 0777, true);
    mkdir($this->tempDir . '/public_html', 0777, true);

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $paths->contentDir = 'content';
    $this->site = new Site(null, $paths);

    Database::disconnect();
    Database::$dataDir = $this->tempDir . '/data';
    Database::connect($this->tempDir . '/data/indieinabox.sqlite');
    $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    Database::getDb()->exec($sql);
});

afterEach(function () {
    Database::disconnect();
    FileUtils::recursiveRmdir($this->tempDir);
});

test('DemoCommand reports status', function () {
    $cmd = new DemoCommand($this->site);

    ob_start();
    $code = $cmd->execute(['indieinabox.php', 'demo', 'status']);
    $output = ob_get_clean();

    expect($code)->toBe(0)
        ->and($output)->toContain('IndieInABox Demo Sandbox Status:')
        ->and($output)->toContain('Demo Mode:      INACTIVE');
});

test('DemoCommand seeds demo environment via CLI', function () {
    $cmd = new DemoCommand($this->site);

    ob_start();
    $code = $cmd->execute([
        'indieinabox.php',
        'demo',
        'seed',
        '--fqdn', 'https://demo.indieinabox.2lp.in',
        '--sitename', 'Sandbox CLI Test',
        '--password', 'secret123',
    ]);
    $output = ob_get_clean();

    expect($code)->toBe(0)
        ->and($output)->toContain('Demo sandbox successfully seeded')
        ->and($output)->toContain('https://demo.indieinabox.2lp.in');

    expect(Database::getSetting('sitename'))->toBe('Sandbox CLI Test');
    expect(file_exists($this->tempDir . '/data/.demo_seed/demo.sqlite'))->toBeTrue();
});

test('DemoCommand resets demo environment via CLI', function () {
    $cmd = new DemoCommand($this->site);

    // Seed first
    $cmd->execute(['indieinabox.php', 'demo', 'seed']);

    // Vandalize
    Database::setSetting('sitename', 'Corrupted Sandbox');
    expect(Database::getSetting('sitename'))->toBe('Corrupted Sandbox');

    // Run CLI reset
    ob_start();
    $code = $cmd->execute(['indieinabox.php', 'demo', 'reset']);
    $output = ob_get_clean();

    expect($code)->toBe(0)
        ->and($output)->toContain('Demo sandbox restored successfully');

    expect(Database::getSetting('sitename'))->toBe('IndieInABox Public Sandbox');
});

test('ConsoleKernel routes demo command', function () {
    $kernel = new ConsoleKernel($this->site);

    ob_start();
    $code = $kernel->handle(['indieinabox.php', 'demo', 'status']);
    $output = ob_get_clean();

    expect($code)->toBe(0)
        ->and($output)->toContain('IndieInABox Demo Sandbox Status:');
});
