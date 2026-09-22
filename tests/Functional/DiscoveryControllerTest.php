<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Http\WebRouter;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_discovery_controller_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/content', 0777, true);
    mkdir($this->tempDir . '/data/microsub/inbox/timeline', 0777, true);
    mkdir($this->tempDir . '/public_html', 0777, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir . '/data';
    Database::connect(':memory:');
    $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database.sql');
    Database::getDb()->exec($sql);

    $paths = new Paths($this->tempDir);
    $paths->contentDir = $this->tempDir . '/content';
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);

    // Seed one public post
    $post = <<<'MD'
---
title: Welcome to the Fediverse
date: 2026-09-21 15:30:00
tags:
  - introduction
---
A federated exploration post! #hello
MD;
    file_put_contents($this->tempDir . '/content/intro.md', $post);

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    Database::disconnect();
    FileUtils::recursiveRmdir($this->tempDir);
});

it('renders HTML view on /discover and /global by default', function () {
    $router = new WebRouter($this->site);

    $_SERVER['REQUEST_URI'] = '/discover';
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toContain('<!DOCTYPE html>');
    expect($output)->toContain('Global Timeline');
    expect($output)->toContain('Welcome to the Fediverse');
    expect($output)->toContain('#introduction');

    // /global route works identically
    $_SERVER['REQUEST_URI'] = '/global';
    ob_start();
    $router->handleRequest();
    $globalOutput = ob_get_clean();

    expect($globalOutput)->toContain('Global Timeline');
});

it('returns JF2 JSON feed format with content negotiation or format parameter', function () {
    $router = new WebRouter($this->site);

    // Test with query parameter ?format=jf2
    $_SERVER['REQUEST_URI'] = '/discover';
    $_GET['format'] = 'jf2';

    ob_start();
    $router->handleRequest();
    $jsonOutput = ob_get_clean();

    $data = json_decode($jsonOutput, true);
    expect($data)->toBeArray();
    expect($data['type'])->toBe('feed');
    expect($data['total'])->toBe(1);
    expect(count($data['children']))->toBe(1);
    expect($data['children'][0]['name'])->toBe('Welcome to the Fediverse');

    // Test with HTTP Accept header
    $_GET = [];
    $_SERVER['HTTP_ACCEPT'] = 'application/jf2+json';
    ob_start();
    $router->handleRequest();
    $acceptOutput = ob_get_clean();

    $acceptData = json_decode($acceptOutput, true);
    expect($acceptData)->toBeArray();
    expect($acceptData['type'])->toBe('feed');
});

it('returns RSS 2.0 XML with format=rss or Accept: application/rss+xml', function () {
    $router = new WebRouter($this->site);

    $_SERVER['REQUEST_URI'] = '/discover';
    $_GET['format'] = 'rss';

    ob_start();
    $router->handleRequest();
    $rssOutput = ob_get_clean();

    expect($rssOutput)->toContain('<?xml version="1.0" encoding="UTF-8"?>');
    expect($rssOutput)->toContain('<rss version="2.0"');
    expect($rssOutput)->toContain('<title>Welcome to the Fediverse</title>');
    expect($rssOutput)->toContain('<content:encoded>');
});
