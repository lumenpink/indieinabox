<?php

declare(strict_types=1);

use Indieinabox\Support\FileUtils;
use Indieinabox\Theme\ThemeManager;
use Indieinabox\Theme\ThemeStarterService;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_theme_starter_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

it('lists official starter themes with rich metadata', function () {
    $themes = ThemeStarterService::getOfficialThemes();

    expect($themes)->toBeArray();
    expect($themes)->toHaveKeys(['cyberpunk-dark', 'minimal-reader', 'digital-garden']);

    foreach (['cyberpunk-dark', 'minimal-reader', 'digital-garden'] as $slug) {
        $meta = $themes[$slug];
        expect($meta['slug'])->toBe($slug);
        expect($meta['name'])->toBeString()->not->toBeEmpty();
        expect($meta['version'])->toBeString()->not->toBeEmpty();
        expect($meta['description'])->toBeString()->not->toBeEmpty();
        expect($meta['preview_colors'])->toBeArray()->not->toBeEmpty();
    }
});

it('validates official theme manifests against ThemeManager::checkManifest', function () {
    $sourceDir = ThemeStarterService::getStartersSourceDir();

    foreach (['cyberpunk-dark', 'minimal-reader', 'digital-garden'] as $slug) {
        $manifestPath = $sourceDir . '/' . $slug . '/theme.json';
        expect(file_exists($manifestPath))->toBeTrue();

        $content = file_get_contents($manifestPath);
        $manifest = json_decode($content, true);
        expect($manifest)->toBeArray();

        $check = ThemeManager::checkManifest($manifest);
        expect($check['valid'])->toBeTrue();
        expect($check['errors'])->toBeEmpty();
    }
});

it('fails gracefully when installing non-existent theme', function () {
    $targetDir = $this->tempDir . '/installed_themes';
    $result = ThemeStarterService::installOfficialTheme('non-existent-theme-xyz', $targetDir);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain("does not exist");
});

it('successfully installs an official theme into target directory', function () {
    $targetDir = $this->tempDir . '/installed_themes';
    $result = ThemeStarterService::installOfficialTheme('cyberpunk-dark', $targetDir);

    expect($result['success'])->toBeTrue();
    expect($result['slug'])->toBe('cyberpunk-dark');

    $installedPath = $targetDir . '/cyberpunk-dark';
    expect(is_dir($installedPath))->toBeTrue();
    expect(file_exists($installedPath . '/theme.json'))->toBeTrue();
    expect(file_exists($installedPath . '/views/home.php'))->toBeTrue();
    expect(file_exists($installedPath . '/views/page.php'))->toBeTrue();
    expect(file_exists($installedPath . '/static/css/style.css'))->toBeTrue();
});

it('creates standalone zip package for download', function () {
    $zipFile = $this->tempDir . '/minimal-reader.zip';
    $result = ThemeStarterService::createZipPackage('minimal-reader', $zipFile);

    expect($result)->toBe($zipFile);
    expect(file_exists($zipFile))->toBeTrue();
    expect(filesize($zipFile))->toBeGreaterThan(100);

    // Verify ZIP contents using ZipArchive if available
    if (class_exists(\ZipArchive::class)) {
        $zip = new \ZipArchive();
        $openResult = $zip->open($zipFile);
        expect($openResult)->toBeTrue();
        expect($zip->locateName('minimal-reader/theme.json'))->not->toBeFalse();
        expect($zip->locateName('minimal-reader/views/home.php'))->not->toBeFalse();
        $zip->close();
    }
});

it('fails gracefully when packaging non-existent theme', function () {
    $zipFile = $this->tempDir . '/bogus.zip';
    $result = ThemeStarterService::createZipPackage('bogus-theme-404', $zipFile);

    expect($result)->toBeNull();
    expect(file_exists($zipFile))->toBeFalse();
});

