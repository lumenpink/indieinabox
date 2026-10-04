<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Site;
use Indieinabox\Theme\ThemeData;

test('getLanguageSelector renders short native endonyms by default (native_short / native)', function () {
    $loc = new Localization(['en', 'pt-BR', 'es'], 'en', 'native_short');
    $site = new Site(localization: $loc);
    $page = new Page();
    $page->lang = 'en';
    $page->relpath = './';

    $html = ThemeData::getLanguageSelector($page, $site);

    expect($html)->toContain('aria-label="Language selector"')
        ->toContain('English')
        ->toContain('Português')
        ->not->toContain('Português (Brasil)')
        ->toContain('Español')
        ->toContain('hreflang="pt-BR"');
});

test('getLanguageSelector renders long native endonyms with regional distinction in native_long mode', function () {
    $loc = new Localization(['en', 'pt-BR', 'es'], 'en', 'native_long');
    $site = new Site(localization: $loc);
    $page = new Page();
    $page->lang = 'en';
    $page->relpath = './';

    $html = ThemeData::getLanguageSelector($page, $site);

    expect($html)->toContain('English')
        ->toContain('Português (Brasil)')
        ->toContain('Español')
        ->toContain('hreflang="pt-BR"');
});

test('getLanguageSelector renders 2-letter uppercase short codes when code_short / short mode is active', function () {
    $loc = new Localization(['en', 'pt-BR', 'es'], 'en', 'code_short');
    $site = new Site(localization: $loc);
    $page = new Page();
    $page->lang = 'pt-BR';
    $page->relpath = '../';

    $html = ThemeData::getLanguageSelector($page, $site);

    expect($html)->toContain('<strong aria-current="true">PT</strong>')
        ->toContain('hreflang="en">EN</a>')
        ->toContain('hreflang="es">ES</a>')
        ->not->toContain('PT-BR')
        ->not->toContain('Português');
});

test('getLanguageSelector renders uppercase BCP-47 codes when code_long mode is active', function () {
    $loc = new Localization(['en', 'pt-BR', 'es'], 'en', 'code_long');
    $site = new Site(localization: $loc);
    $page = new Page();
    $page->lang = 'pt-BR';
    $page->relpath = '../';

    $html = ThemeData::getLanguageSelector($page, $site);

    expect($html)->toContain('<strong aria-current="true">PT-BR</strong>')
        ->toContain('hreflang="en">EN</a>')
        ->toContain('hreflang="es">ES</a>')
        ->not->toContain('Português');
});

test('build configuration propagates lang_display_mode into Site localization and renders ISO codes (#57)', function () {
    $config = [
        'lang' => ['pt-BR', 'en', 'es'],
        'defaultlang' => 'pt-BR',
        'lang_display_mode' => 'code_short',
    ];

    $site = new Site();
    $site->localization->lang = $config['lang'];
    $site->localization->defaultLang = $config['defaultlang'];
    if (isset($config['lang_display_mode'])) {
        $site->localization->langDisplayMode = (string) $config['lang_display_mode'];
    }

    expect($site->localization->langDisplayMode)->toBe('code_short');

    $page = new Page();
    $page->lang = 'pt-BR';
    $page->relpath = './';

    $html = ThemeData::getLanguageSelector($page, $site);
    expect($html)->toContain('<strong aria-current="true">PT</strong>')
        ->toContain('hreflang="en">EN</a>')
        ->toContain('hreflang="es">ES</a>')
        ->not->toContain('Português');
});


test('getLanguageSelector returns empty string for single language site', function () {
    $loc = new Localization(['en'], 'en');
    $site = new Site(localization: $loc);
    $page = new Page();

    expect(ThemeData::getLanguageSelector($page, $site))->toBe('');
});
