<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Database;
use Indieinabox\Services\ConfigurationService;
use Indieinabox\Site;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_cfg_srv_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/data');
    Database::$dataDir = $this->tempDir . '/data';

    $dbPath = Database::$dataDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS kinds (kind_key TEXT PRIMARY KEY, config_json TEXT)");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS translations (lang TEXT, phrase_key TEXT, phrase_value TEXT, PRIMARY KEY(lang, phrase_key))");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS url_translations (lang TEXT, slug_key TEXT, slug_value TEXT, PRIMARY KEY(lang, slug_key))");

    $this->service = new ConfigurationService(Database::getDb());
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    \Indieinabox\Core\Container::getInstance()->flush();
    \Indieinabox\Support\FileUtils::recursiveRmdir($this->tempDir);
});

test('ConfigurationService bootstraps initial site settings and credentials', function () {
    $this->service->bootstrap('secret123', 'My Awesome Blog', 'https://awesome.example');

    $settings = $this->service->getSettings();
    expect($settings['sitename'])->toBe('My Awesome Blog');
    expect($settings['fqdn'])->toBe('https://awesome.example');
    expect(password_verify('secret123', (string) $settings['indieauth_password']))->toBeTrue();
});

test('ConfigurationService saves and retrieves kinds and translations', function () {
    // Kinds
    $kinds = [
        'note' => ['content_dir' => 'notes', 'has_title' => false],
        'article' => ['content_dir' => 'articles', 'has_title' => true],
    ];
    $this->service->saveKinds($kinds);
    $storedKinds = $this->service->getKinds();
    expect($storedKinds)->toHaveKey('note');
    expect($storedKinds)->toHaveKey('article');

    // Translations
    $translations = [
        'pt' => ['Articles' => 'Artigos', 'Notes' => 'Notas'],
    ];
    $this->service->saveTranslations($translations);
    $storedTrans = $this->service->getTranslations();
    expect($storedTrans)->toHaveKey('Articles');
    expect($storedTrans['Articles']['pt'])->toBe('Artigos');

    // URL Translations
    $urlTrans = [
        'pt' => ['articles' => 'artigos'],
    ];
    $this->service->saveUrlTranslations($urlTrans);
    $storedUrlTrans = $this->service->getUrlTranslations();
    expect($storedUrlTrans)->toHaveKey('articles');
    expect($storedUrlTrans['articles']['pt'])->toBe('artigos');
});

test('ConfigurationService detects pretty links support in cli-server', function () {
    expect($this->service->detectPrettyLinksSupport())->toBeTrue();
});
