<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Site;
use Indieinabox\Theme\ThemeData;

test('getLanguageSelector renders native endonyms by default', function () {
    $loc = new Localization(['en', 'pt-BR', 'es'], 'en', 'native');
    $site = new Site(localization: $loc);
    $page = new Page();
    $page->lang = 'en';
    $page->relpath = './';

    $html = ThemeData::getLanguageSelector($page, $site);

    expect($html)->toContain('aria-label="Language selector"')
        ->toContain('English')
        ->toContain('Português (Brasil)')
        ->toContain('Español')
        ->toContain('hreflang="pt-BR"');
});

test('getLanguageSelector renders 2-letter uppercase short codes when short mode is active', function () {
    $loc = new Localization(['en', 'pt-BR', 'es'], 'en', 'short');
    $site = new Site(localization: $loc);
    $page = new Page();
    $page->lang = 'pt-BR';
    $page->relpath = '../';

    $html = ThemeData::getLanguageSelector($page, $site);

    expect($html)->toContain('<strong aria-current="true">PT</strong>')
        ->toContain('hreflang="en">EN</a>')
        ->toContain('hreflang="es">ES</a>')
        ->not->toContain('PT-BR')
        ->not->toContain('Português (Brasil)');
});

test('getLanguageSelector returns empty string for single language site', function () {
    $loc = new Localization(['en'], 'en');
    $site = new Site(localization: $loc);
    $page = new Page();

    expect(ThemeData::getLanguageSelector($page, $site))->toBe('');
});
