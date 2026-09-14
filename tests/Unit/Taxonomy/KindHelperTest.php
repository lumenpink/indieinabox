<?php

declare(strict_types=1);

use Indieinabox\Core\Container;
use Indieinabox\Page;
use Indieinabox\Site;
use Indieinabox\Site\Localization;
use Indieinabox\Taxonomy\KindHelper;

beforeEach(function () {
    $this->site = new Site();
    $this->site->localization->defaultLang = 'en';
    $this->site->config['kinds'] = [
        'article' => [
            'content_dir' => 'articles',
            'has_title' => true,
            'show_on_home' => true,
            'title' => ['en' => 'Articles']
        ],
        'note' => [
            'content_dir' => 'notes',
            'has_title' => false,
            'show_on_home' => true,
            'title' => ['en' => 'Notes']
        ]
    ];
    Container::getInstance()->instance(Site::class, $this->site);
});

afterEach(function () {
    Container::getInstance()->flush();
});

it('retrieves kind config with defaults', function () {
    $config = KindHelper::getKindConfig('article');

    expect($config['content_dir'])->toBe('articles')
        ->and($config['has_title'])->toBeTrue()
        ->and($config['show_on_home'])->toBeTrue();
});

it('classifies page kinds correctly', function () {
    global $site, $kindspath;
    $backupSite = $site ?? null;
    $backupKindspath = $kindspath ?? null;

    $site = new Site(
        null,
        null,
        null,
        new Localization('en'),
        null
    );
    $kindspath = [
        "article" => ["artigos", "articles", "articulos"],
        "note" => ["notes", "notas"],
        "photo" => ["fotos", "photos"]
    ];

    $pageArray = ['kind' => 'recipe', 'slug' => 'recipes/cake', 'lang' => 'en'];
    expect(KindHelper::kind($pageArray))->toBe(['localized' => 'recipe', 'kind' => 'recipe']);

    $pageSlugMatch = ['slug' => 'articles/my-post', 'lang' => 'en'];
    expect(KindHelper::kind($pageSlugMatch))->toBe(['localized' => 'articles', 'kind' => 'article']);

    $site = $backupSite;
    $kindspath = $backupKindspath;
});

it('formats kind labels and links', function () {
    $page = new Page(null, null, null);
    $page->lang = 'en';
    $page->relpath = './';

    $label = KindHelper::kindLabel('note', 'en');
    expect($label)->toBe('Notes');

    $link = KindHelper::kindLink($page, 'article');
    expect($link)->toContain('href="./articles/"')
        ->and($link)->toContain('ARTICLES');
});

it('filters generic pages with removeGeneric', function () {
    $page = new Page(null, null, null);
    $page->kind = 'generic';
    expect(KindHelper::removeGeneric($page))->toBeFalse();

    $articlePage = new Page(null, null, null);
    $articlePage->kind = 'article';
    expect(KindHelper::removeGeneric($articlePage))->toBeTrue();
});
