<?php

declare(strict_types=1);

use Indieinabox\ShortlinkManager;
use Indieinabox\Page;

beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir() . '/shortlink_cache_' . uniqid();
    mkdir($this->cacheDir);
});

afterEach(function () {
    if (is_dir($this->cacheDir)) {
        array_map('unlink', glob("$this->cacheDir/*.*"));
        rmdir($this->cacheDir);
    }
});

it('returns shortlink from cache if it exists', function () {
    $page = new Page(null, null, null);
    $page->slug = 'test-post';
    $config = ['enabled' => true, 'server' => 'https://0x0.st', 'parameter' => 'shorten'];

    $fqdn = 'https://lumen.pink';
    $url = rtrim($fqdn, '/') . '/' . ltrim($page->slug, '/');
    
    // Create cache file
    $cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . md5($url) . '.txt';
    file_put_contents($cacheFile, 'https://0x0.st/cached');

    $manager = new ShortlinkManager($this->cacheDir);
    $shortlink = $manager->getShortlink($page, $fqdn, $config);

    expect($shortlink)->toBe('https://0x0.st/cached');
});

it('returns null if shortlink config is disabled', function () {
    $page = new Page(null, null, null);
    $page->slug = 'test-post';
    $config = ['enabled' => false];
    
    $manager = new ShortlinkManager($this->cacheDir);
    $shortlink = $manager->getShortlink($page, 'https://lumen.pink', $config);

    expect($shortlink)->toBeNull();
});

it('fetches shortlink from server on cache miss and writes cache', function () {
    $page = new Page(null, null, null);
    $page->slug = 'test-post';
    $config = ['enabled' => true, 'server' => 'http://localhost:9999/down', 'parameter' => 'shorten'];
    
    $manager = new ShortlinkManager($this->cacheDir);
    $shortlink = @$manager->getShortlink($page, 'https://lumen.pink', $config);

    expect($shortlink)->toBeNull();
});
