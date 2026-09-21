<?php

declare(strict_types=1);

namespace Tests\Unit\SiteBuilder;

use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\IndexPublisher;
use Indieinabox\SiteBuilder\PagePublisher;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_tag_indexing_test_' . bin2hex(random_bytes(6));
    @mkdir($this->tempDir, 0755, true);

    $paths = new Paths($this->tempDir);
    $this->site = new Site(null, $paths);
    $this->site->paths->outputDirHtml = 'public_html';
    $this->site->paths->outputDirGemini = 'public_gemini';
    $this->site->paths->outputDirGopher = 'public_gopher';
    $this->site->paths->themeDir = 'theme';
    @mkdir($this->tempDir . '/theme/views', 0755, true);
    file_put_contents($this->tempDir . '/theme/views/page.php', '<html><body><?= $page->content->content ?? "" ?></body></html>');
    $this->site->localization = new Localization();
    $this->site->localization->lang = ['en'];
    $this->site->localization->defaultLang = 'en';
    $this->site->metadata->fqdn = 'https://example.com';
    $this->site->options->prettylinks = true;

    global $site;
    $site = $this->site;

    $this->pages = new Pages();
    $this->pagePublisher = new PagePublisher($this->site, $this->pages);
    $this->indexPublisher = new IndexPublisher($this->site, $this->pagePublisher);
});

afterEach(function () {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        FileUtils::recursiveRmdir($this->tempDir);
    }
});

test('compileTaxonomyIndexes automatically extracts hashtags from raw post content', function () {
    $pageWithHashtags = Page::fromArray([
        'title' => 'Post with hashtags in body',
        'kind' => 'note',
        'slug' => 'notes/post-hashtags/',
        'rawBody' => 'Just publishing a note with #indieweb and #posse hashtags in the text body.',
        'date' => new \DateTime('2026-09-21'),
        'lang' => 'en',
    ]);
    $this->pages->add($pageWithHashtags);

    $this->indexPublisher->publishTaxonomies($this->pages);

    $htmlBase = $this->tempDir . '/public_html';

    // Verify /tags/ and /tag/ global index pages exist
    expect(file_exists($htmlBase . '/tags/index.html'))->toBeTrue();
    expect(file_exists($htmlBase . '/tag/index.html'))->toBeTrue();

    // Verify individual hashtag pages exist under /tag/<hashtag>/ and /tags/<hashtag>/
    expect(file_exists($htmlBase . '/tag/indieweb/index.html'))->toBeTrue();
    expect(file_exists($htmlBase . '/tags/indieweb/index.html'))->toBeTrue();
    expect(file_exists($htmlBase . '/tag/posse/index.html'))->toBeTrue();
    expect(file_exists($htmlBase . '/tags/posse/index.html'))->toBeTrue();

    // Verify content of tag index lists the post
    $indiewebHtml = file_get_contents($htmlBase . '/tag/indieweb/index.html');
    expect($indiewebHtml)->toContain('post-hashtags');
});

test('compileTaxonomyIndexes ignores No Tag and No Category placeholders', function () {
    $pageDefault = Page::fromArray([
        'title' => 'Post with default tags',
        'kind' => 'note',
        'slug' => 'notes/default-tags/',
        'rawBody' => 'Simple note without any hashtags.',
        'date' => new \DateTime('2026-09-21'),
        'lang' => 'en',
        'tags' => ['No Tag'],
    ]);
    $this->pages->add($pageDefault);

    $this->indexPublisher->publishTaxonomies($this->pages);

    $htmlBase = $this->tempDir . '/public_html';
    expect(file_exists($htmlBase . '/tag/no-tag/index.html'))->toBeFalse();
    expect(file_exists($htmlBase . '/tags/no-tag/index.html'))->toBeFalse();
});
