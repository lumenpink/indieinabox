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
