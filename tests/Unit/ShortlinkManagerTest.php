<?php

declare(strict_types=1);

use Indieinabox\ShortlinkManager;
use Indieinabox\Page;

$cacheDir = '';

beforeEach(function () use (&$cacheDir) {
    $cacheDir = sys_get_temp_dir() . '/shortlink_cache_' . uniqid();
    mkdir($cacheDir);
});

afterEach(function () use (&$cacheDir) {
    if (is_dir($cacheDir)) {
        array_map('unlink', glob("$cacheDir/*.*"));
        rmdir($cacheDir);
    }
});

it('returns shortlink from cache if it exists', function () use (&$cacheDir) {
    $page = new Page(null, null, null);
    $page->slug = 'test-post';
    $config = ['enabled' => true, 'server' => 'https://0x0.st', 'parameter' => 'shorten'];

    $fqdn = 'https://lumen.pink';
    $url = rtrim($fqdn, '/') . '/' . ltrim($page->slug, '/');
    
    // Create cache file
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . md5($url) . '.txt';
    file_put_contents($cacheFile, 'https://0x0.st/cached');

    $manager = new ShortlinkManager($cacheDir);
    $shortlink = $manager->getShortlink($page, $fqdn, $config);

    expect($shortlink)->toBe('https://0x0.st/cached');
});

it('returns null if shortlink config is disabled', function () use (&$cacheDir) {
    $page = new Page(null, null, null);
    $page->slug = 'test-post';
    $config = ['enabled' => false];
    
    $manager = new ShortlinkManager($cacheDir);
    $shortlink = $manager->getShortlink($page, 'https://lumen.pink', $config);

    expect($shortlink)->toBe('https://lumen.pink/s/debe30d8');
});

it('fetches shortlink from server on cache miss and writes cache', function () use (&$cacheDir) {
    $page = new Page(null, null, null);
    $page->slug = 'test-post';
    $config = ['enabled' => true, 'server' => 'http://localhost:9999/down', 'parameter' => 'shorten'];
    
    $manager = new ShortlinkManager($cacheDir);
    $shortlink = @$manager->getShortlink($page, 'https://lumen.pink', $config);

    expect($shortlink)->toBe('https://lumen.pink/s/debe30d8');
});
