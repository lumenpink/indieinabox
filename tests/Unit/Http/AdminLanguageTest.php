<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Http\Controllers\AdminController;
use Indieinabox\Repositories\SqliteSettingsRepository;
use Indieinabox\Site\Site;
use Indieinabox\Views\Admin\ConfigView;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_lang_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    Database::getDb()->exec($sql);

    $this->site = new Site();
    $this->site->metadata->fqdn = 'https://lang.example';
    $this->site->metadata->indieauthPassword = 'test-password-hash';

    $repo = new SqliteSettingsRepository();
    $repo->set('indieauth_password', 'test-password-hash');

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION = ['admin_authenticated' => true];
    $_FILES = [];
    http_response_code(200);

    Container::getInstance()->flush();
    Container::getInstance()->instance(Site::class, $this->site);
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    Container::getInstance()->flush();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('AdminController handles set_main_lang to promote language to index 0 and defaultlang', function () {
    $repo = new SqliteSettingsRepository();
    $repo->set('lang', ['en', 'pt', 'es']);
    $repo->set('defaultlang', 'en');

    $admin = new AdminController($this->site, null, null, null, null, $repo);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'lang' => ['en', 'pt', 'es'],
        'set_main_lang' => 'pt',
        'sitename' => 'Test Site',
    ];

    ob_start();
    $admin->config();
    ob_end_clean();

    $savedLangs = $repo->get('lang');
    $defaultLang = $repo->get('defaultlang');

    expect($savedLangs)->toBe(['pt', 'en', 'es'])
        ->and($defaultLang)->toBe('pt');
});

test('AdminController handles move_up_lang and move_down_lang', function () {
    $repo = new SqliteSettingsRepository();
    $repo->set('lang', ['en', 'pt', 'es']);
    $admin = new AdminController($this->site, null, null, null, null, $repo);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'lang' => ['en', 'pt', 'es'],
        'move_up_lang' => 'es',
    ];

    ob_start();
    $admin->config();
    ob_end_clean();

    expect($repo->get('lang'))->toBe(['en', 'es', 'pt']);

    $_POST = [
        'lang' => ['en', 'es', 'pt'],
        'move_down_lang' => 'en',
    ];

    ob_start();
    $admin->config();
    ob_end_clean();

    expect($repo->get('lang'))->toBe(['es', 'en', 'pt'])
        ->and($repo->get('defaultlang'))->toBe('es');
});

test('AdminController prevents removing the only remaining language', function () {
    $repo = new SqliteSettingsRepository();
    $repo->set('lang', ['en']);
    $admin = new AdminController($this->site, null, null, null, null, $repo);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'lang' => ['en'],
        'remove_lang' => 'en',
    ];

    ob_start();
    $admin->config();
    ob_end_clean();

    expect($repo->get('lang'))->toBe(['en'])
        ->and($repo->get('defaultlang'))->toBe('en');
});

test('ConfigView renders Main (Default) badge, Make Main button, and reorder arrows', function () {
    $config = [
        'lang' => ['pt', 'en', 'es'],
        'defaultlang' => 'pt',
        'sitename' => 'My Site',
        'fqdn' => 'https://example.org',
    ];

    $html = ConfigView::renderConfig($this->site, $config);

    expect($html)->toContain('Main (Default)')
        ->toContain('Make Main')
        ->toContain('name="move_up_lang"')
        ->toContain('name="move_down_lang"')
        ->toContain('Sub-language');
});

test('AdminController auto-fills translations from locale dictionary when a language is added', function () {
    $repo = new SqliteSettingsRepository();
    $repo->set('lang', ['en']);
    $repo->set('defaultlang', 'en');

    $admin = new AdminController($this->site, null, null, null, null, $repo);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'lang' => ['en', 'pt'],
        'sitename' => 'Test Site',
    ];

    ob_start();
    $admin->config();
    ob_end_clean();

    $translations = $repo->getTranslations();
    expect($translations['Home']['pt'] ?? null)->toBe('Início')
        ->and($translations['Recent posts']['pt'] ?? null)->toBe('Publicações recentes');
});

