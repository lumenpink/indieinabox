<?php

declare(strict_types=1);

use Indieinabox\Page;
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
