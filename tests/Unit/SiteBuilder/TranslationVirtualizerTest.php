<?php

declare(strict_types=1);

use Indieinabox\Page;
use Indieinabox\Pages;
use Indieinabox\Site;
use Indieinabox\Site\Localization;
use Indieinabox\SiteBuilder\TranslationVirtualizer;

beforeEach(function () {
    $this->site = new Site();
    $this->site->localization = new Localization();
    $this->site->localization->lang = ['en', 'pt'];
    $this->site->localization->defaultLang = 'en';
    $this->site->options->translation_parity = 'full';
    $this->site->options->translation_auto = 'pseudo';
    $this->virtualizer = new TranslationVirtualizer($this->site);
});

it('skips virtualization when only one language is configured', function () {
    $this->site->localization->lang = ['en'];
    $pages = new Pages();
    $page = Page::fromArray([
        'lang' => 'en',
        'kind' => 'page',
        'nick' => 'about',
        'slug' => '/about/',
    ]);
    $pages->add($page);

    $this->virtualizer->virtualize($pages);
    expect($pages)->toHaveCount(1);
});

it('throws RuntimeException when translation_auto is disabled and translation is missing', function () {
    $this->site->options->translation_auto = 'disabled';
    $pages = new Pages();
    $page = Page::fromArray([
        'lang' => 'en',
        'kind' => 'page',
        'nick' => 'about',
        'slug' => '/about/',
    ]);
    $pages->add($page);

    expect(fn() => $this->virtualizer->virtualize($pages))->toThrow(\RuntimeException::class);
});

it('creates virtualized page with pseudo-translated title when missing', function () {
    $pages = new Pages();
    $page = Page::fromArray([
        'lang' => 'en',
        'kind' => 'page',
        'nick' => 'about',
        'slug' => '/about/',
        'title' => 'About us',
        'content' => 'Hello',
    ]);
    $pages->add($page);

    $this->virtualizer->virtualize($pages);

    expect($pages)->toHaveCount(2);

    $ptPage = null;
    foreach ($pages as $p) {
        if ($p->lang === 'pt') {
            $ptPage = $p;
            break;
        }
    }

    expect($ptPage)->not->toBeNull();
    expect($ptPage->title)->toBe('[PT] About us');
});

it('applies pseudo translation to body when kind does not have title', function () {
    $this->site->config['kinds']['note']['has_title'] = false;

    $page = Page::fromArray([
        'lang' => 'en',
        'kind' => 'note',
        'nick' => 'note-1',
        'slug' => 'notes/note-1/',
        'title' => '',
        'content' => 'Just note body',
        'rawBody' => 'Just note body',
    ]);

    $this->virtualizer->pseudoTranslate($page, 'pt');

    expect($page->content->content)->toBe('[PT] Just note body');
    expect($page->content->rawBody)->toBe('[PT] Just note body');
});

it('respects parity rule from-main-only', function () {
    $this->site->options->translation_parity = 'from-main-only';
    $pages = new Pages();
    $pagePt = Page::fromArray([
        'lang' => 'pt',
        'kind' => 'page',
        'nick' => 'teste',
        'slug' => '/pt/teste/',
    ]);
    $pages->add($pagePt);

    $this->virtualizer->virtualize($pages);
    expect($pages)->toHaveCount(1);
});
