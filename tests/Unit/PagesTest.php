<?php

declare(strict_types=1);

use Indieinabox\Pages;
use Indieinabox\Page;

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
