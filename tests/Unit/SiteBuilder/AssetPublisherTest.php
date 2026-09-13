<?php

declare(strict_types=1);

use Indieinabox\Helper;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\SiteBuilder\AssetPublisher;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_test_publisher_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $this->paths = new Paths(
        $this->tempDir,
        'public_html',
        'public_gemini',
        'public_gopher',
        'public_media',
        'content',
        'resources'
    );
    $this->site = new Site(null, $this->paths);
    $this->publisher = new AssetPublisher($this->site);
});

afterEach(function () {
    Helper::recursiveRmdir($this->tempDir);
});

it('returns false when publishing static files from non-existent directory without DefaultTheme', function () {
    $result = $this->publisher->publishStaticFiles($this->tempDir . '/non_existent_dir');
    expect($result)->toBeFalse();
});

it('copies static files to outputDirHtml successfully', function () {
    $staticDir = $this->tempDir . '/static_source';
    mkdir($staticDir, 0777, true);
    file_put_contents($staticDir . '/test.txt', 'hello static');

    $result = $this->publisher->publishStaticFiles($staticDir);
    expect($result)->toBeTrue();

    $dest = $this->tempDir . '/public_html/test.txt';
    expect(file_exists($dest))->toBeTrue();
    expect(file_get_contents($dest))->toBe('hello static');
});

it('handles publishViewAssets when directory does not exist', function () {
    // Should not throw or fail
    $this->publisher->publishViewAssets($this->tempDir . '/non_existent_views');
    expect(is_dir($this->tempDir . '/public_html/assets'))->toBeFalse();
});

it('copies view assets from assets subdirectory', function () {
    $viewsDir = $this->tempDir . '/views';
    $assetsDir = $viewsDir . '/assets';
    mkdir($assetsDir, 0777, true);
    file_put_contents($assetsDir . '/style.css', 'body { color: red; }');

    $this->publisher->publishViewAssets($viewsDir);

    $dest = $this->tempDir . '/public_html/assets/style.css';
    expect(file_exists($dest))->toBeTrue();
    expect(file_get_contents($dest))->toBe('body { color: red; }');
});

it('copies content and microsub media files', function () {
    $contentMedia = $this->tempDir . '/content/media';
    mkdir($contentMedia, 0777, true);
    file_put_contents($contentMedia . '/photo.jpg', 'fake image bytes');

    $microsubMedia = $this->tempDir . '/data/microsub/media';
    mkdir($microsubMedia, 0777, true);
    file_put_contents($microsubMedia . '/avatar.png', 'fake avatar bytes');

    $this->publisher->publishMedia();

    $destPhoto = $this->tempDir . '/public_media/photo.jpg';
    $destAvatar = $this->tempDir . '/public_media/microsub/avatar.png';

    expect(file_exists($destPhoto))->toBeTrue();
    expect(file_exists($destAvatar))->toBeTrue();
    expect(file_get_contents($destPhoto))->toBe('fake image bytes');
    expect(file_get_contents($destAvatar))->toBe('fake avatar bytes');
});

it('performs garbage collection removing orphaned files and directories', function () {
    $htmlDir = $this->tempDir . '/public_html';
    $subDir = $htmlDir . '/subdir';
    mkdir($subDir, 0777, true);

    $keptFile = $htmlDir . '/kept.html';
    $orphanedFile = $htmlDir . '/orphaned.html';
    $orphanedInSubDir = $subDir . '/orphaned_nested.html';

    file_put_contents($keptFile, 'keep me');
    file_put_contents($orphanedFile, 'remove me');
    file_put_contents($orphanedInSubDir, 'remove me nested');

    $manifest = [
        $keptFile => true,
    ];

    $this->publisher->garbageCollect($manifest);

    expect(file_exists($keptFile))->toBeTrue();
    expect(file_exists($orphanedFile))->toBeFalse();
    expect(file_exists($orphanedInSubDir))->toBeFalse();
    // Subdir should be removed as it became empty
    expect(is_dir($subDir))->toBeFalse();
});
