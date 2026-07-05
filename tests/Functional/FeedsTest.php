<?php

declare(strict_types=1);

namespace Tests\Functional;

use Indieinabox\Page;
use Indieinabox\Site;
use Indieinabox\Site\Metadata;
use Indieinabox\Feeds\FeedManager;

beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->feedManager = new FeedManager();
    $this->metadata = new Metadata();
    $this->metadata->sitename = "Test Site";
    $this->metadata->description = "Test Description";
    $this->metadata->fqdn = "https://example.com";
    
    // Setup global site for Helper::getKindConfig
    global $site;
    $site = new Site();
    $site->config = [
        'kinds' => [
            'article' => ['show_on_home' => true],
            'page' => ['show_on_home' => false]
        ]
    ];
    
    // Setup temporary directory for output
    $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'indieinabox_feeds_test_' . uniqid();
    mkdir($this->tempDir);
    
    // Create some pages
    $this->pages = [];
    for ($i = 1; $i <= 5; $i++) {
        $this->pages[] = Page::fromArray([
            'title' => "Post $i",
            'slug' => "post-$i",
            'kind' => 'article',
            'date' => new \DateTime("2026-07-0$i 12:00:00"),
            'content' => "<p>Content of post $i</p>",
            'tags' => []
        ]);
    }
});

afterEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    // Cleanup temp dir
    $files = array_diff(scandir($this->tempDir), ['.', '..']);
    foreach ($files as $file) {
        unlink($this->tempDir . DIRECTORY_SEPARATOR . $file);
    }
    rmdir($this->tempDir);
});

it('generates a valid RSS feed with correct limits', function () {
    /** @var \Tests\TestCase|mixed $this */
    $rssFile = $this->tempDir . DIRECTORY_SEPARATOR . 'rss.xml';
    
    // Limit to 3
    $this->feedManager->generateRss($this->pages, $rssFile, "https://example.com", $this->metadata, 3);
    
    expect(file_exists($rssFile))->toBeTrue();
    $xml = simplexml_load_file($rssFile);
    expect((string)$xml->channel->title)->toBe("Test Site");
    expect((string)$xml->channel->description)->toBe("Test Description");
    
    // Verify count is 3
    $items = $xml->channel->item;
    expect(count($items))->toBe(3);
    
    // Verify sorting (descending, so Post 5 should be first)
    expect((string)$items[0]->title)->toBe("Post 5");
    expect((string)$items[2]->title)->toBe("Post 3");
});

it('generates a valid Atom feed with correct limits', function () {
    /** @var \Tests\TestCase|mixed $this */
    $atomFile = $this->tempDir . DIRECTORY_SEPARATOR . 'atom.xml';
    
    // Limit to 2
    $this->feedManager->generateAtom($this->pages, $atomFile, "https://example.com", $this->metadata, 2);
    
    expect(file_exists($atomFile))->toBeTrue();
    $xml = simplexml_load_file($atomFile);
    // Note: SimpleXML needs namespace handling, but we can do simple checks
    $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
    
    $title = $xml->xpath('//atom:title');
    expect((string)$title[0])->toBe("Test Site");
    
    $entries = $xml->xpath('//atom:entry');
    expect(count($entries))->toBe(2);
});

it('respects the limit 0 (infinite)', function () {
    /** @var \Tests\TestCase|mixed $this */
    $rssFile = $this->tempDir . DIRECTORY_SEPARATOR . 'rss.xml';
    
    // Limit to 0
    $this->feedManager->generateRss($this->pages, $rssFile, "https://example.com", $this->metadata, 0);
    
    $xml = simplexml_load_file($rssFile);
    $items = $xml->channel->item;
    // Should include all 5
    expect(count($items))->toBe(5);
});

it('respects hide_on_rss logic in frontmatter', function () {
    /** @var \Tests\TestCase|mixed $this */
    $rssFile = $this->tempDir . DIRECTORY_SEPARATOR . 'rss.xml';
    $atomFile = $this->tempDir . DIRECTORY_SEPARATOR . 'atom.xml';
    
    // Add page with hide_on_rss = true
    $this->pages[] = Page::fromArray([
        'title' => "Hidden True",
        'slug' => "hidden-true",
        'kind' => 'article',
        'date' => new \DateTime("2026-07-06 12:00:00"),
        'content' => "<p>Hidden</p>",
        'tags' => [],
        'hide_on_rss' => true
    ]);
    
    // Add page with hide_on_rss = 'yes'
    $this->pages[] = Page::fromArray([
        'title' => "Hidden Yes",
        'slug' => "hidden-yes",
        'kind' => 'article',
        'date' => new \DateTime("2026-07-07 12:00:00"),
        'content' => "<p>Hidden</p>",
        'tags' => [],
        'hide_on_rss' => 'yes'
    ]);
    
    // Add page with hide_on_rss = false
    $this->pages[] = Page::fromArray([
        'title' => "Visible False",
        'slug' => "visible-false",
        'kind' => 'article',
        'date' => new \DateTime("2026-07-08 12:00:00"),
        'content' => "<p>Visible</p>",
        'tags' => [],
        'hide_on_rss' => false
    ]);

    // Total physical pages = 8.
    // "Hidden True" and "Hidden Yes" should be excluded, leaving 6.
    
    $this->feedManager->generateRss($this->pages, $rssFile, "https://example.com", $this->metadata, 0);
    $this->feedManager->generateAtom($this->pages, $atomFile, "https://example.com", $this->metadata, 0);
    
    $rssXml = simplexml_load_file($rssFile);
    $rssItems = $rssXml->channel->item;
    expect(count($rssItems))->toBe(6);
    
    // The most recent allowed one is "Visible False" (since date is 2026-07-08)
    expect((string)$rssItems[0]->title)->toBe("Visible False");
    
    $atomXml = simplexml_load_file($atomFile);
    $atomXml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
    $atomEntries = $atomXml->xpath('//atom:entry');
    expect(count($atomEntries))->toBe(6);
});

it('removes drafts and generic pages', function () {
    /** @var \Tests\TestCase|mixed $this */
    $rssFile = $this->tempDir . DIRECTORY_SEPARATOR . 'rss.xml';
    
    $this->pages[] = Page::fromArray([
        'title' => "Draft Post",
        'slug' => "draft",
        'kind' => 'article',
        'date' => new \DateTime("2026-07-06 12:00:00"),
        'content' => "",
        'tags' => ['draft']
    ]);
    
    $this->pages[] = Page::fromArray([
        'title' => "Page Kind",
        'slug' => "page",
        'kind' => 'page',
        'date' => new \DateTime("2026-07-07 12:00:00"),
        'content' => "",
        'tags' => []
    ]);
    
    $this->feedManager->generateRss($this->pages, $rssFile, "https://example.com", $this->metadata, 0);
    
    $xml = simplexml_load_file($rssFile);
    $items = $xml->channel->item;
    
    // Should still be 5, since draft and page kinds (show_on_home = false) are excluded
    expect(count($items))->toBe(5);
});
