<?php

declare(strict_types=1);

use Indieinabox\Page;
use Indieinabox\SiteBuilder\SiteBuilder;
use Indieinabox\Site;
use Indieinabox\Site\Localization;

beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->site = new Site();
    $this->site->localization = new Localization();
    $this->site->localization->lang = ['en', 'pt'];
    $this->site->localization->defaultLang = 'en';
    $this->site->options->translation_parity = 'full';
    $this->site->options->translation_auto = 'pseudo';
    $GLOBALS['site'] = $this->site;
});

it('throws exception when translation_auto is disabled and parity is missing', function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->site->options->translation_auto = 'disabled';
    
    $page = Page::fromArray([
        'lang' => 'en',
        'kind' => 'page',
        'nick' => 'about',
        'slug' => '/about/'
    ]);
    $pages = new \Indieinabox\Pages();
    $pages->add($page);
    $builder = new SiteBuilder($this->site, $pages);

    // This should throw because 'pt' translation is missing and auto is disabled
    expect(fn() => $builder->getTranslationVirtualizer()->virtualize($pages))->toThrow(\RuntimeException::class);
});

it('generates pseudo translations when translation_auto is pseudo', function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->site->options->translation_auto = 'pseudo';
    
    $page = Page::fromArray([
        'lang' => 'en',
        'kind' => 'page',
        'nick' => 'about',
        'slug' => '/about/',
        'title' => 'About us',
        'content' => 'Hello'
    ]);
    
    $pages = new \Indieinabox\Pages();
    $pages->add($page);
    $builder = new SiteBuilder($this->site, $pages);

    $builder->getTranslationVirtualizer()->virtualize($pages);
    
    // Should have 2 pages (the original EN and the virtual PT)
    expect($pages)->toHaveCount(2);
    
    // PT page should have [PT] in title
    $ptPage = null;
    foreach ($pages as $p) {
        if ($p->lang === 'pt') {
            $ptPage = $p;
            break;
        }
    }
    
    expect($ptPage)->not->toBeNull();
    expect($ptPage->title)->toContain('[PT]');
});

it('respects parity rules (from-main-only)', function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->site->options->translation_parity = 'from-main-only';
    
    // A sublang page (pt) - should NOT trigger virtualization to EN
    $pagePt = Page::fromArray([
        'lang' => 'pt',
        'kind' => 'page',
        'nick' => 'teste',
        'slug' => '/pt/teste/'
    ]);
    
    $pages = new \Indieinabox\Pages();
    $pages->add($pagePt);
    $builder = new SiteBuilder($this->site, $pages);

    $builder->getTranslationVirtualizer()->virtualize($pages);
    
    expect($pages)->toHaveCount(1); // No EN page generated
});

it('getLanguageLinks falls back to home when parity is disabled and translation is missing', function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->site->options->translation_parity = 'disabled';
    
    $page = Page::fromArray([
        'lang' => 'en',
        'kind' => 'page',
        'nick' => 'about',
        'slug' => '/about/'
    ]);
    
    $pages = new \Indieinabox\Pages();
    $pages->add($page);
    $builder = new SiteBuilder($this->site, $pages);
    
    // We don't call virtualizeMissingLanguages because it does nothing when disabled

    $links = $builder->getPagePublisher()->getLanguageLinks($page);
    
    // En exists, should link to it
    expect($links['en'])->toBe('/about/');
    
    // Pt does NOT exist, should fallback to /pt/
    expect($links['pt'])->toBe('/pt/');
});
