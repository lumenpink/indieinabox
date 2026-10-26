<?php

declare(strict_types=1);

use Indieinabox\Support\FileUtils;
use Indieinabox\Site\Site;
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

it('validates theme manifests and flags directory traversal', function () {
    $manager = new ThemeManager();

    // Missing required fields
    $res1 = $manager->validateManifest([]);
    expect($res1['valid'])->toBeFalse();
    expect($res1['errors'])->toContain('Theme name is required and must be a non-empty string.');

    // Traversal attempt
    $res2 = $manager->validateManifest([
        'name' => 'Evil Theme',
        'version' => '1.0.0',
        'templates' => ['../../etc/passwd'],
    ]);
    expect($res2['valid'])->toBeFalse();
    expect(implode(' ', $res2['errors']))->toContain('directory traversal');

    // Valid manifest
    $res3 = $manager->validateManifest([
        'name' => 'Clean Theme',
        'version' => '1.2.0',
        'templates' => ['views/index.php'],
    ]);
    expect($res3['valid'])->toBeTrue();
    expect($res3['errors'])->toBeEmpty();
});

it('lists installed themes including default and custom directory themes', function () {
    $themesDir = $this->tempDir . '/themes';
    mkdir($themesDir . '/minimal', 0777, true);

    file_put_contents($themesDir . '/minimal/theme.json', json_encode([
        'name' => 'Minimal Dark',
        'version' => '2.0.0',
        'author' => 'ThemeAuthor',
        'description' => 'A clean minimalist theme',
    ]));

    $manager = new ThemeManager();
    $themes = $manager->listThemes($themesDir);

    expect(count($themes))->toBeGreaterThanOrEqual(2);
    $slugs = array_column($themes, 'slug');
    expect($slugs)->toContain('default');
    expect($slugs)->toContain('minimal');

    $minimal = array_values(array_filter($themes, fn($t) => $t['slug'] === 'minimal'))[0];
    expect($minimal['name'])->toBe('Minimal Dark');
    expect($minimal['version'])->toBe('2.0.0');
});

it('installs theme from ZIP archive and activates it', function () {
    $themesDir = $this->tempDir . '/themes_install';
    mkdir($themesDir, 0777, true);

    // Create a mock theme folder to zip
    $sourceTheme = $this->tempDir . '/src_theme/my-clean-theme';
    mkdir($sourceTheme . '/views', 0777, true);
    file_put_contents($sourceTheme . '/theme.json', json_encode([
        'name' => 'My Clean Theme',
        'slug' => 'my-clean-theme',
        'version' => '1.0.0',
    ]));
    file_put_contents($sourceTheme . '/views/home.php', '<h1>Clean Home</h1>');

    $zipFile = $this->tempDir . '/theme.zip';
    if (class_exists(\ZipArchive::class)) {
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFile($sourceTheme . '/theme.json', 'theme.json');
            $zip->addFile($sourceTheme . '/views/home.php', 'views/home.php');
            $zip->close();
        }
    } else {
        exec(sprintf('cd %s && zip -r %s . 2>&1', escapeshellarg($this->tempDir . '/src_theme'), escapeshellarg($zipFile)));
    }

    $manager = new ThemeManager();
    $result = $manager->installFromZip($zipFile, $themesDir);

    expect($result['success'])->toBeTrue();
    expect($result['slug'])->toBe('my-clean-theme');
    expect(file_exists($themesDir . '/my-clean-theme/views/home.php'))->toBeTrue();

    // Activate theme
    $activated = $manager->activateTheme('my-clean-theme', $this->site, $themesDir);
    expect($activated)->toBeTrue();
    expect($this->site->paths->themeDir)->toBe($themesDir . '/my-clean-theme');
});
