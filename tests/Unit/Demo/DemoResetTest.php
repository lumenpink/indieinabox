<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Demo\DemoService;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_demo_test_' . uniqid();
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

it('seeds the demo environment and creates golden snapshot', function () {
    $service = new DemoService($this->site);
    $result = $service->seed([
        'fqdn' => 'https://demo.indieinabox.2lp.in',
        'sitename' => 'IndieInABox Public Sandbox',
        'password' => 'demo123',
    ]);

    expect($result['success'])->toBeTrue()
        ->and(file_exists($this->tempDir . '/data/.demo_seed/demo.sqlite'))->toBeTrue()
        ->and(is_dir($this->tempDir . '/data/.demo_seed/content'))->toBeTrue();

    // Check that settings are stored
    expect(Database::getSetting('sitename'))->toBe('IndieInABox Public Sandbox')
        ->and((string) Database::getSetting('demo_mode'))->toBe('1');

    // Check sample content was generated
    $contentFiles = glob($this->tempDir . '/content/*/*.md');
    expect(count($contentFiles))->toBeGreaterThan(0);
});

it('resets the demo environment, rolling back database and content changes', function () {
    $service = new DemoService($this->site);
    $service->seed([
        'fqdn' => 'https://demo.indieinabox.2lp.in',
        'sitename' => 'IndieInABox Public Sandbox',
        'password' => 'demo123',
    ]);

    // Simulate visitor vandalism / modifications
    Database::getSetting('sitename'); // verify read
    Database::getDb()->exec("UPDATE settings SET value = 'Vandalized Title' WHERE key = 'sitename'");
    expect(Database::getSetting('sitename'))->toBe('Vandalized Title');

    $vandalFile = $this->tempDir . '/content/articles/vandalism.md';
    if (!is_dir(dirname($vandalFile))) {
        mkdir(dirname($vandalFile), 0777, true);
    }
    file_put_contents($vandalFile, "---\ntitle: Spam Post\n---\nVandalized content");
    expect(file_exists($vandalFile))->toBeTrue();

    // Perform rollback/reset
    $resetResult = $service->reset();
    expect($resetResult['success'])->toBeTrue();

    // Re-verify that database was restored
    Database::disconnect();
    Database::connect($this->tempDir . '/data/indieinabox.sqlite');
    expect(Database::getSetting('sitename'))->toBe('IndieInABox Public Sandbox');

    // Re-verify that vandalized file was purged
    expect(file_exists($vandalFile))->toBeFalse();
});

it('reports demo sandbox status and determines when rollback is due', function () {
    $service = new DemoService($this->site);
    
    // Before seeding or demo mode
    expect($service->shouldReset())->toBeFalse();

    $service->seed();
    $status = $service->getStatus();

    expect($status['demo_mode'])->toBeTrue()
        ->and($status['interval_seconds'])->toBe(3600)
        ->and($status['last_reset'])->toBeGreaterThan(0)
        ->and($service->shouldReset())->toBeFalse();

    // Fast-forward last_reset to simulated past (>3600s ago)
    Database::setSetting('demo_last_reset', (string) (time() - 3601));
    expect($service->shouldReset())->toBeTrue();
});
