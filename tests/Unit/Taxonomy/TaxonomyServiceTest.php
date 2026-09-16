<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Site\Site;
use Indieinabox\Taxonomy\TaxonomyService;
use Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface;

beforeEach(function () {
    $this->site = new Site();
    $this->site->localization->defaultLang = 'en';
    $this->site->localization->lang = ['en', 'pt'];
    $this->site->config['kinds'] = [
        'article' => [
            'content_dir' => ['en' => 'articles', 'pt' => 'artigos'],
            'has_title' => true,
            'show_on_home' => true,
            'title' => ['en' => 'Articles', 'pt' => 'Artigos'],
        ],
        'note' => [
            'content_dir' => 'notes',
            'has_title' => false,
            'show_on_home' => true,
            'title' => ['en' => 'Notes', 'pt' => 'Notas'],
        ],
    ];
    $this->taxonomyService = new TaxonomyService($this->site);
});

it('implements TaxonomyServiceInterface', function () {
    expect($this->taxonomyService)->toBeInstanceOf(TaxonomyServiceInterface::class);
});

it('resolves kind configuration with sensible defaults', function () {
    $config = $this->taxonomyService->getKindConfig('article');

    expect($config['has_title'])->toBeTrue()
        ->and($config['show_on_home'])->toBeTrue()
        ->and($config['display_mode'])->toBe('default');
});

it('resolves kind from page object and slug', function () {
    $page = Page::fromArray(['kind' => 'article', 'slug' => 'articles/hello', 'lang' => 'en']);
    $result = $this->taxonomyService->resolveKind($page);

    expect($result['kind'])->toBe('article')
        ->and($result['localized'])->toBe('articles');
});

it('resolves localized kind folder across languages', function () {
    expect($this->taxonomyService->getKindFolder('article', 'en'))->toBe('articles')
        ->and($this->taxonomyService->getKindFolder('article', 'pt'))->toBe('artigos');
});

it('resolves localized kind label', function () {
    expect($this->taxonomyService->getKindLabel('article', 'en'))->toBe('Articles')
        ->and($this->taxonomyService->getKindLabel('article', 'pt'))->toBe('Artigos');
});

it('generates kind link markup', function () {
    $page = Page::fromArray(['relpath' => '../', 'lang' => 'en']);
    $link = $this->taxonomyService->getKindLink($page, 'article');

    expect($link)->toContain('href="../articles/"')
        ->and($link)->toContain('ARTICLES');
});

it('determines listing eligibility based on show_on_home', function () {
    $article = Page::fromArray(['kind' => 'article']);
    $page = Page::fromArray(['kind' => 'page']);

    expect($this->taxonomyService->isListingEligible($article))->toBeTrue()
        ->and($this->taxonomyService->isListingEligible($page))->toBeFalse();
});
