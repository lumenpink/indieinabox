<?php

declare(strict_types=1);

use bovigo\vfs\vfsStream;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Localization;
use Indieinabox\Page;

beforeEach(function () {
    global $site, $translations, $page, $p;
    global $backupTranslations, $backupSite, $backupPage, $backupP;

    vfsStream::setup('root', null, [
        'data' => [
            'translations.php' => "<?php\nglobal \$translations;\n\$translations = [];\n?>"
        ]
    ]);

    $backupTranslations = $translations ?? [];
    $backupSite = $site ?? null;
    $backupPage = $page ?? null;
    $backupP = $p ?? null;

    $reflection = new \ReflectionClass(\Indieinabox\Database::class);
    $property = $reflection->getProperty('db');
    $property->setAccessible(true);
    $property->setValue(null, null);

    \Indieinabox\Database::connect(':memory:');
    $sql = file_get_contents(dirname(__DIR__, 2) . '/database.sql');
    \Indieinabox\Database::getDb()->exec($sql);

    $site = new Site(
        null,
        new Paths('vfs://root'),
        null,
        new Localization('en'),
        null
    );
    
    $site->config['translations'] = [
        'Welcome Friend' => ['es' => 'Bienvenido Amigo'],
        'Hello' => ['es' => 'Hola'],
    ];

    $page = null;
    $p = null;
});

afterEach(function () {
    global $site, $translations, $page, $p;
    global $backupTranslations, $backupSite, $backupPage, $backupP;

    $translations = $backupTranslations;
    $site = $backupSite;
    $page = $backupPage;
    $p = $backupP;
});

it('translates existing keys for non-default languages', function () {
    expect(translate('Welcome Friend', 'es'))->toBe('Bienvenido Amigo');
    expect(t('Hello', 'es'))->toBe('Hola');
});

it('returns original text for the default language', function () {
    expect(translate('Welcome Friend', 'en'))->toBe('Welcome Friend');
    expect(t('Hello', 'en'))->toBe('Hello');
});

it('resolves active language from global page structures', function () {
    global $page, $p;

    // From Page object
    $page = Page::fromArray(['lang' => 'es']);
    expect(translate('Hello'))->toBe('Hola');

    // From array fallback
    $page = ['lang' => 'es'];
    expect(translate('Hello'))->toBe('Hola');

    // From $p structure
    $page = null;
    $p = Page::fromArray(['lang' => 'es']);
    expect(translate('Hello'))->toBe('Hola');
});

it('adds missing keys to database dynamically', function () {
    global $site;

    expect(translate('Missing key example', 'es'))->toBe('Missing key example');
    expect($site->config['translations']['Missing key example']['es'])->toBe('');

    $db = \Indieinabox\Database::getDb();
    $stmt = $db->query("SELECT * FROM translations WHERE phrase_key = 'Missing key example' AND lang = 'es'");
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    expect($row)->not->toBeFalse();
    expect($row['phrase_value'])->toBe('');
});

it('formats translations using lowercase and slugize helpers', function () {
    global $page, $site;
    $page = ['lang' => 'es'];
    $site->config['translations']['Welcome Friend']['es'] = 'bienvenido amigo';
    $site->config['translations']['Hello']['es'] = 'hola';

    expect(translateLowercase('Welcome Friend'))->toBe('bienvenido amigo')
        ->and(tl('Hello'))->toBe('hola')
        ->and(translateSlugize('Welcome Friend'))->toBe('bienvenido-amigo')
        ->and(ts('Hello'))->toBe('hola');
});
