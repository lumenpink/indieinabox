<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Site\Site;
use Indieinabox\Taxonomy\SeoMetadataResolver;
use Indieinabox\Taxonomy\Contracts\SeoMetadataResolverInterface;

beforeEach(function () {
    $this->site = new Site();
    $this->site->metadata->fqdn = 'https://example.org';
    $this->site->metadata->sitename = 'Example Site';
    $this->resolver = new SeoMetadataResolver($this->site);
});

it('implements SeoMetadataResolverInterface', function () {
    expect($this->resolver)->toBeInstanceOf(SeoMetadataResolverInterface::class);
});

it('extracts description from page content when metadata description is empty', function () {
    $page = Page::fromArray([
        'title' => 'Sample Post',
        'kind' => 'article',
        'content' => '<p>This is a <strong>rich</strong> description of the article.</p>',
    ]);

    $seo = $this->resolver->resolve($page);

    expect($seo['description'])->toBe('This is a rich description of the article.')
        ->and($seo['schema_type'])->toBe('BlogPosting');
});

it('falls back to default site image when no image is found in post', function () {
    $page = Page::fromArray([
        'title' => 'No Image Post',
        'kind' => 'note',
        'content' => 'Plain text content',
    ]);

    $seo = $this->resolver->resolve($page);

    expect($seo['image'])->toBe('https://example.org/media/default.png')
        ->and($seo['image_alt'])->toBe('Example Site')
        ->and($seo['schema_type'])->toBe('SocialMediaPosting');
});

it('converts relative image URL in post content to absolute URL', function () {
    $page = Page::fromArray([
        'title' => 'Image Post',
        'kind' => 'photo',
        'relpath' => '../../',
        'content' => '<p><img src="/media/photo.jpg" alt="A photo"></p>',
    ]);

    $seo = $this->resolver->resolve($page);

    expect($seo['image'])->toBe('https://example.org/media/photo.jpg')
        ->and($seo['image_alt'])->toBe('A photo')
        ->and($seo['schema_type'])->toBe('MediaObject');
});
