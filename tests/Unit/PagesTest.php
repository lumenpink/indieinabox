<?php

declare(strict_types=1);

use Indieinabox\Page\Pages;
use Indieinabox\Page\Page;

it('initializes empty Pages collection', function () {
    $pages = new Pages();
    expect($pages->all())->toBeEmpty();
});

it('adds Page objects with default slug key', function () {
    $pages = new Pages();
    $page = Page::fromArray(['title' => 'My Page', 'slug' => 'my-page']);

    $pages->add($page);

    expect($pages->get('my-page'))->toBe($page)
        ->and($pages->all())->toHaveKey('my-page');
});

it('adds Page objects with custom key identifier', function () {
    $pages = new Pages();
    $page = Page::fromArray(['title' => 'My Page', 'slug' => 'my-page']);

    $pages->add($page, 'custom-id');

    expect($pages->get('custom-id'))->toBe($page)
        ->and($pages->get('my-page'))->toBeNull();
});

it('supports adding raw array structures', function () {
    $pages = new Pages();
    $pageArray = ['title' => 'Raw Page', 'slug' => 'raw-page'];

    $pages->add($pageArray);

    expect($pages['raw-page'])->toBe($pageArray);
});

it('filters pages by kind and by language', function () {
    $pages = new Pages();
    $p1 = Page::fromArray(['slug' => 'p1', 'kind' => 'article', 'lang' => 'en']);
    $p2 = Page::fromArray(['slug' => 'p2', 'kind' => 'note', 'lang' => 'en']);
    $p3 = Page::fromArray(['slug' => 'p3', 'kind' => 'article', 'lang' => 'pt']);

    $pages->add($p1);
    $pages->add($p2);
    $pages->add($p3);

    expect($pages->filterByKind('article'))->toHaveCount(2)
        ->and($pages->filterByLanguage('en'))->toHaveCount(2)
        ->and($pages->filterByLanguage('pt'))->toHaveCount(1);
});

it('retrieves recent posts sorted descending by date with limit and custom filter', function () {
    $pages = new Pages();
    $old = Page::fromArray(['slug' => 'old', 'date' => 1000, 'lang' => 'en', 'kind' => 'article']);
    $med = Page::fromArray(['slug' => 'med', 'date' => 2000, 'lang' => 'en', 'kind' => 'article']);
    $new = Page::fromArray(['slug' => 'new', 'date' => 3000, 'lang' => 'en', 'kind' => 'note']);
    $ptPost = Page::fromArray(['slug' => 'pt', 'date' => 4000, 'lang' => 'pt', 'kind' => 'article']);

    $pages->add($old);
    $pages->add($med);
    $pages->add($new);
    $pages->add($ptPost);

    $recent = $pages->getRecentPosts(2, 'en');
    expect($recent)->toHaveCount(2)
        ->and($recent[0]->slug)->toBe('new')
        ->and($recent[1]->slug)->toBe('med');

    // With custom filter
    $filtered = $pages->getRecentPosts(5, 'en', fn($p) => $p->kind === 'article');
    expect($filtered)->toHaveCount(2)
        ->and($filtered[0]->slug)->toBe('med')
        ->and($filtered[1]->slug)->toBe('old');
});

it('converts Page instance to associative array using toArray', function () {
    $page = Page::fromArray([
        'title' => 'Sample Post',
        'slug' => 'sample-post',
        'kind' => 'article',
        'lang' => 'en',
        'tags' => ['tech', 'indieweb'],
        'content' => 'Hello universe',
    ]);

    $array = $page->toArray();
    expect($array)->toBeArray()
        ->and($array['slug'])->toBe('sample-post')
        ->and($array['kind'])->toBe('article')
        ->and($array['lang'])->toBe('en')
        ->and($array['tags'])->toBe(['tech', 'indieweb'])
        ->and($array['frontmatter']['title'])->toBe('Sample Post');
});

it('queries Pages collection using SpecificationInterface and composite specifications', function () {
    $pages = new Pages();
    $p1 = Page::fromArray(['slug' => 'art-en', 'kind' => 'article', 'lang' => 'en', 'tags' => ['php', 'web']]);
    $p2 = Page::fromArray(['slug' => 'art-pt', 'kind' => 'article', 'lang' => 'pt', 'tags' => ['php']]);
    $p3 = Page::fromArray(['slug' => 'note-en', 'kind' => 'note', 'lang' => 'en', 'tags' => ['quick']]);

    $pages->add($p1);
    $pages->add($p2);
    $pages->add($p3);

    $articleSpec = new \Indieinabox\Specifications\Content\KindSpecification('article');
    $articles = $pages->query($articleSpec);
    expect($articles)->toHaveCount(2)
        ->and(array_keys($articles))->toEqualCanonicalizing(['art-en', 'art-pt']);

    $enArticlesSpec = $articleSpec->and(new \Indieinabox\Specifications\Content\LanguageSpecification('en'));
    $enArticles = $pages->query($enArticlesSpec);
    expect($enArticles)->toHaveCount(1)
        ->and(array_keys($enArticles))->toBe(['art-en']);

    $phpTagSpec = new \Indieinabox\Specifications\Content\TagSpecification(['php']);
    $phpPosts = $pages->query($phpTagSpec);
    expect($phpPosts)->toHaveCount(2)
        ->and(array_keys($phpPosts))->toEqualCanonicalizing(['art-en', 'art-pt']);
});

