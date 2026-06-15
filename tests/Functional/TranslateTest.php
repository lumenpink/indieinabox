<?php

declare(strict_types=1);

use org\bovigo\vfs\vfsStream;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Localization;
use Indieinabox\Page;

beforeEach(function () {
    global $site, $translations, $page, $p;

    $this->vfsRoot = vfsStream::setup('root', null, [
        'data' => [
            'translations.php' => "<?php\nglobal \$translations;\n\$translations = [];\n?>"
        ]
    ]);

    $this->originalTranslations = $translations ?? [];
    $this->originalSite = $site ?? null;
    $this->originalPage = $page ?? null;
    $this->originalP = $p ?? null;

    $translations = [
        'es' => [
            'Welcome Friend' => 'Bienvenido Amigo',
            'Hello' => 'Hola',
        ]
    ];

    $site = new Site(
        null,
        new Paths('vfs://root'),
        null,
        new Localization('en'),
        null
    );

    $page = null;
    $p = null;
});

afterEach(function () {
    global $site, $translations, $page, $p;
    $translations = $this->originalTranslations;
    $site = $this->originalSite;
    $page = $this->originalPage;
    $p = $this->originalP;
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

it('adds missing keys to translations file dynamically', function () {
    global $translations;

    expect(translate('Missing key example', 'es'))->toBe('Missing key example');
    expect($translations['es'])->toHaveKey('Missing key example');
    expect($translations['es']['Missing key example'])->toBe('');

    $virtualFilePath = 'vfs://root/data/translations.php';
    expect(file_exists($virtualFilePath))->toBeTrue();

    $content = file_get_contents($virtualFilePath);
    expect($content)->toContain('Missing key example');
});

it('formats translations using lowercase and slugize helpers', function () {
    global $page;
    $page = ['lang' => 'es'];

    expect(translateLowercase('Welcome Friend'))->toBe('bienvenido amigo')
        ->and(tl('Hello'))->toBe('hola')
        ->and(translateSlugize('Welcome Friend'))->toBe('bienvenido-amigo')
        ->and(ts('Hello'))->toBe('hola');
});
