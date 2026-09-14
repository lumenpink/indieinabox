<?php

declare(strict_types=1);

use Indieinabox\Support\FileUtils;
use Indieinabox\Page;
use Indieinabox\Pages;
use Indieinabox\ParserInterface;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\SiteBuilder\ContentScanner;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_scanner_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $paths = new Paths($this->tempDir);
    $this->site = new Site(null, $paths);
    $this->pages = new Pages();
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

it('initializes with default parser and returns it', function () {
    $scanner = new ContentScanner($this->site);
    expect($scanner->getParser())->toBeInstanceOf(ParserInterface::class);
});

it('accepts custom parser implementation', function () {
    $dummyParser = new class implements ParserInterface {
        public function parse(string $filepath): ?Page
        {
            return null;
        }
    };
    $scanner = new ContentScanner($this->site, $dummyParser);
    expect($scanner->getParser())->toBe($dummyParser);
});

it('handles non-existent scan directory gracefully', function () {
    $scanner = new ContentScanner($this->site);
    $scanner->scan($this->tempDir . '/non_existent', $this->pages);
    expect($this->pages)->toHaveCount(0);
});

it('scans markdown files and ignores system files and ignored directories', function () {
    $contentDir = $this->tempDir . '/content';
    mkdir($contentDir . '/sub', 0777, true);
    mkdir($this->tempDir . '/vendor', 0777, true);

    // Valid markdown
    file_put_contents($contentDir . '/post1.md', "---\ntitle: Post 1\nkind: article\n---\nHello");
    file_put_contents($contentDir . '/sub/post2.md', "---\ntitle: Post 2\nkind: note\n---\nWorld");

    // Ignored files: intro.md, dotfile, underscore
    file_put_contents($contentDir . '/intro.md', "intro");
    file_put_contents($contentDir . '/.hidden.md', "hidden");
    file_put_contents($contentDir . '/_draft.md', "draft");

    // Ignored directory
    file_put_contents($this->tempDir . '/vendor/ignored.md', "ignored");

    $scanner = new ContentScanner($this->site);
    $scanner->scan($this->tempDir, $this->pages);

    expect($this->pages)->toHaveCount(2);
});

it('ensures mandatory homepage creates a fallback home page when missing', function () {
    $scanner = new ContentScanner($this->site);
    $scanner->ensureMandatoryHomepage($this->pages);

    expect($this->pages)->toHaveCount(1);
    $home = null;
    foreach ($this->pages as $p) {
        $home = $p;
        break;
    }
    expect($home)->not->toBeNull();
    expect($home->slug)->toBe('/');
    expect($home->layout)->toBe('home');
});

it('forces layout to home when homepage already exists', function () {
    $existingHome = Page::fromArray([
        'slug' => '/',
        'title' => 'Existing Home',
        'layout' => 'page',
        'content' => 'Home content',
    ]);
    $this->pages->add($existingHome);

    $scanner = new ContentScanner($this->site);
    $scanner->ensureMandatoryHomepage($this->pages);

    expect($this->pages)->toHaveCount(1);
    expect($existingHome->layout)->toBe('home');
});

it('renders raw markdown bodies into final html content for pages', function () {
    $page = Page::fromArray([
        'slug' => 'test-post/',
        'title' => 'Test Post',
        'kind' => 'article',
    ]);
    $page->content = new \Indieinabox\Page\Content();
    $page->content->rawBody = "## Hello World\n\nThis is a **bold** paragraph.";
    $this->pages->add($page);

    $scanner = new ContentScanner($this->site);
    $scanner->renderRawBodies($this->pages);

    expect($page->content->content)->toContain('<h2>Hello World</h2>');
    expect($page->content->content)->toContain('<strong>bold</strong>');
});
