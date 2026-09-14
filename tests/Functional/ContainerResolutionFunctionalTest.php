<?php

declare(strict_types=1);

use Indieinabox\Core\Container;
use Indieinabox\Site;
use Indieinabox\Taxonomy\KindHelper;
use Indieinabox\ThemeManager;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_functional_container_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/resources');
    mkdir($this->tempDir . '/resources/views');

    $this->site = new Site();
    $this->site->paths->themeDir = $this->tempDir . '/resources';
    $this->site->localization->defaultLang = 'pt';
    $this->site->config['kinds']['article']['title']['pt'] = 'Artigos';

    Container::getInstance()->instance(Site::class, $this->site);
    unset($GLOBALS['site']);
});

afterEach(function () {
    Container::getInstance()->flush();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('ThemeManager and KindHelper resolve site state purely from Container without global site', function () {
    expect(isset($GLOBALS['site']))->toBeFalse();

    // 1. ThemeManager view path resolution
    $resolvedPath = ThemeManager::resolveViewPath('custom.php');
    expect($resolvedPath)->toBe($this->tempDir . '/resources/views/custom.php');

    // 2. KindHelper localized label resolution
    $label = KindHelper::kindLabel('article');
    expect($label)->toBe('Artigos');
});
