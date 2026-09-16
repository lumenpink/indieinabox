<?php

declare(strict_types=1);

use Indieinabox\Support\FileUtils;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Theme\ThemeManager;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_theme_mgr_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $paths = new Paths($this->tempDir);
    $paths->themeDir = $this->tempDir . '/my_theme';
    $this->site = new Site(null, $paths);

    global $site;
    $site = $this->site;
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

it('resolves view path using configured themeDir', function () {
    $resolved = ThemeManager::resolveViewPath('includes/header.php');
    expect($resolved)->toBe($this->site->paths->themeDir . '/views/includes/header.php');
});

it('checks if a view exists on disk with hasView', function () {
    $viewsDir = $this->site->paths->themeDir . '/views';
    mkdir($viewsDir, 0777, true);
    $viewFile = $viewsDir . '/custom.php';
    file_put_contents($viewFile, '<h1>Custom</h1>');

    expect(ThemeManager::hasView($viewFile))->toBeTrue();
    expect(ThemeManager::hasView($viewsDir . '/missing.php'))->toBeFalse();
});

it('retrieves view content from disk with getViewContent', function () {
    $viewsDir = $this->site->paths->themeDir . '/views';
    mkdir($viewsDir, 0777, true);
    $viewFile = $viewsDir . '/content.php';
    file_put_contents($viewFile, 'Hello Theme');

    expect(ThemeManager::getViewContent($viewFile))->toBe('Hello Theme');
    expect(ThemeManager::getViewContent($viewsDir . '/missing.php'))->toBeNull();
});

it('renders a view template to string with renderView', function () {
    $viewsDir = $this->site->paths->themeDir . '/views';
    mkdir($viewsDir, 0777, true);
    $viewFile = $viewsDir . '/hello.php';
    file_put_contents($viewFile, 'Hello <?= htmlspecialchars($name) ?>!');

    $output = ThemeManager::renderView($viewFile, ['name' => 'World']);
    expect($output)->toBe('Hello World!');
});

it('loads and executes a view with loadView extracting parameters', function () {
    $viewsDir = $this->site->paths->themeDir . '/views';
    mkdir($viewsDir, 0777, true);
    $viewFile = $viewsDir . '/count.php';
    file_put_contents($viewFile, 'Count: <?= $count ?>');

    ob_start();
    ThemeManager::loadView($viewFile, ['count' => 42]);
    $output = ob_get_clean();

    expect($output)->toBe('Count: 42');
});

it('includes partials using includeView', function () {
    $viewsDir = $this->site->paths->themeDir . '/views/partials';
    mkdir($viewsDir, 0777, true);
    $partialFile = $viewsDir . '/head.php';
    file_put_contents($partialFile, '<title><?= $title ?></title>');

    ob_start();
    ThemeManager::includeView('partials/head.php', ['title' => 'My Site']);
    $output = ob_get_clean();

    expect($output)->toBe('<title>My Site</title>');
});

it('outputs HTML comment when view is missing on disk and no DefaultTheme', function () {
    ob_start();
    ThemeManager::loadView($this->tempDir . '/non_existent.php');
    $output = ob_get_clean();

    expect($output)->toContain('<!-- Theme file not found:');
});
