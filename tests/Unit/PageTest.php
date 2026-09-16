<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Page\Metadata;
use Indieinabox\Page\Content;
use Indieinabox\Page\Localization;

it('creates a Page object with defaults', function () {
    $page = new Page(null, null, null);

    expect($page->slug)->toBe('untitled');
    expect($page->relpath)->toBe('');
    expect($page->date)->toBeInstanceOf(DateTime::class);
    expect($page->metadata)->toBeInstanceOf(Metadata::class);
    expect((string) $page->content)->toBe('Hello World');
    expect($page->lang)->toBe('en');
});

it('supports shortcut property getters and setters', function () {
    $page = new Page(null, null, null);

    // Setters
    $page->lang = 'pt-br';
    $page->title = 'Test Title';
    $page->content = '<p>Custom Content</p>';
    $page->noauthor = true;

    // Getters
    expect($page->lang)->toBe('pt-br')
        ->and($page->title)->toBe('Test Title')
        ->and($page->content)->toBe('<p>Custom Content</p>')
        ->and($page->noauthor)->toBeTrue();

    // Check isset
    expect(isset($page->lang))->toBeTrue()
        ->and(isset($page->title))->toBeTrue()
        ->and(isset($page->nonexistent))->toBeFalse();
});

it('creates Page from array structures', function () {
    $timestamp = 1609459200; // 2021-01-01 00:00:00 UTC
    $data = [
        'title' => 'Page from Array',
        'content' => 'Some markdown content',
        'lang' => 'es',
        'date' => $timestamp,
        'tags' => ['blog', 'tech']
    ];

    $page = Page::fromArray($data);

    expect($page->title)->toBe('Page from Array')
        ->and((string) $page->content)->toBe('Some markdown content')
        ->and($page->lang)->toBe('es')
        ->and($page->tags)->toBe(['blog', 'tech'])
        ->and($page->date->getTimestamp())->toBe($timestamp);
});

it('casts Content object to string', function () {
    $content = new Content('Rendered Output', 'Source Output', []);
    expect((string) $content)->toBe('Rendered Output');
});

test('garden kind receives default tags if missing', function () {
    $data = [
        'kind' => 'garden',
        'title' => 'My Digital Garden'
    ];
    $page = \Indieinabox\Page\Page::fromArray($data);
    expect($page->metadata->flowerbed)->toBe(['general']);
    expect($page->metadata->confidence)->toBe('possible');
    expect($page->metadata->maturity)->toBe('sprout');
    expect($page->metadata->importance)->toBe('trivial');
});

test('garden kind respects provided tags', function () {
    $data = [
        'kind' => 'garden',
        'title' => 'Advanced Garden',
        'flowerbed' => ['tech', 'design'],
        'confidence' => 'certain',
        'maturity' => 'tree',
        'importance' => 'critical'
    ];
    $page = \Indieinabox\Page\Page::fromArray($data);
    expect($page->metadata->flowerbed)->toBe(['tech', 'design']);
    expect($page->metadata->confidence)->toBe('certain');
    expect($page->metadata->maturity)->toBe('tree');
    expect($page->metadata->importance)->toBe('critical');
});

test('Page domain methods provide clean encapsulation', function () {
    $page = Page::fromArray([
        'title' => 'Domain Article',
        'slug' => 'domain-article',
        'kind' => 'article',
        'lang' => 'pt',
        'tags' => ['ddd', 'clean-arch', 'draft'],
    ]);

    expect($page->isDraft())->toBeTrue();
    expect($page->hasTitle())->toBeTrue();
    expect($page->getTitle())->toBe('Domain Article');
    expect($page->getSlug())->toBe('domain-article');
    expect($page->getKind())->toBe('article');
    expect($page->getLanguage())->toBe('pt');
    expect($page->hasTag('ddd'))->toBeTrue();
    expect($page->hasTag('nonexistent'))->toBeFalse();
    expect($page->getDate())->toBeInstanceOf(DateTime::class);
});

test('Pages collection aggregate filters and queries domain pages', function () {
    $pages = new \Indieinabox\Page\Pages();
    $page1 = Page::fromArray(['title' => 'Post 1', 'slug' => 'post-1', 'kind' => 'article', 'lang' => 'en']);
    $page2 = Page::fromArray(['title' => 'Post 2', 'slug' => 'post-2', 'kind' => 'note', 'lang' => 'pt']);

    $pages->add($page1);
    $pages->add($page2);

    expect($pages->count())->toBe(2);
    expect($pages->has('post-1'))->toBeTrue();
    expect($pages->has('missing'))->toBeFalse();
    expect($pages->find('post-1'))->toBe($page1);
    expect($pages->find('missing'))->toBeNull();

    $articles = $pages->filterByKind('article');
    expect(count($articles))->toBe(1);
    expect(array_keys($articles))->toContain('post-1');

    $ptPages = $pages->filterByLanguage('pt');
    expect(count($ptPages))->toBe(1);
    expect(array_keys($ptPages))->toContain('post-2');

    $pages->remove('post-1');
    expect($pages->has('post-1'))->toBeFalse();
    expect($pages->count())->toBe(1);
});

