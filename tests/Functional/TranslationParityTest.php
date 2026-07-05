<?php

declare(strict_types=1);

use Indieinabox\Page;
use Indieinabox\SiteBuilder;
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

    $reflection = new \ReflectionClass(SiteBuilder::class);
    $method = $reflection->getMethod('virtualizeMissingLanguages');
    $method->setAccessible(true);
    
    // This should throw because 'pt' translation is missing and auto is disabled
    expect(fn() => $method->invoke($builder))->toThrow(\RuntimeException::class);
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

    $reflection = new \ReflectionClass(SiteBuilder::class);
    $method = $reflection->getMethod('virtualizeMissingLanguages');
    $method->setAccessible(true);
    
    // Should pass without throwing
    $method->invoke($builder);
    
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

    $reflection = new \ReflectionClass(SiteBuilder::class);
    $method = $reflection->getMethod('virtualizeMissingLanguages');
    $method->setAccessible(true);
    
    $method->invoke($builder);
    
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

    $reflection = new \ReflectionClass(SiteBuilder::class);
    $method = $reflection->getMethod('getLanguageLinks');
    $method->setAccessible(true);
    
    $links = $method->invoke($builder, $page);
    
    // En exists, should link to it
    expect($links['en'])->toBe('/about/');
    
    // Pt does NOT exist, should fallback to /pt/
    expect($links['pt'])->toBe('/pt/');
});
