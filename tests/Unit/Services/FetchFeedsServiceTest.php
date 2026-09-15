<?php

declare(strict_types=1);

use Indieinabox\Database;
use Indieinabox\Services\FetchFeedsService;
use Indieinabox\Feeds\Contracts\FeedParserInterface;
use Indieinabox\Microsub\ExtendedEntry;

$tempDir = __DIR__ . '/tmp_fetch_feeds_unit';

beforeEach(function () use ($tempDir) {
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    Database::disconnect();
    $dbPath = $tempDir . '/test.sqlite';
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
    Database::$dataDir = $tempDir . '/data';
    Database::connect($dbPath);
    $db = Database::getDb();
    $schema = file_get_contents(__DIR__ . '/../../../database.sql');
    $db->exec($schema);
});

afterEach(function () use ($tempDir) {
    Database::disconnect();
    Database::$dataDir = '';

    if (is_dir($tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($tempDir);
    }
});

describe('FetchFeedsService', function () {
    it('manages strategy parsers dynamically', function () {
        $service = new FetchFeedsService();
        $parsers = $service->getParsers();
        expect($parsers)->toHaveKeys(['twtxt', 'jsonfeed', 'rss', 'atom']);

        $customParser = new class implements FeedParserInterface {
            public function getFormat(): string { return 'custom'; }
            public function supports(string $content): bool { return str_contains($content, 'CUSTOM_FEED'); }
            public function parse(string $content, string $feedUrl): array { return []; }
        };

        $service->addParser($customParser);
        expect($service->getParsers())->toHaveKey('custom');
        expect($service->findParser('CUSTOM_FEED_DATA'))->toBe($customParser);
    });

    it('parses and persists feed entries to channel disk storage', function () {
        $service = new FetchFeedsService();

        $rssContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Author Name</title>
        <item>
            <title>Feed Entry 1</title>
            <guid>entry-guid-123</guid>
            <link>https://example.com/post/1</link>
            <pubDate>Mon, 14 Sep 2026 12:00:00 GMT</pubDate>
            <description>Hello world content</description>
        </item>
    </channel>
</rss>
XML;

        $savedCount = $service->fetchSubscription('inbox', 'https://example.com/rss.xml', $rssContent);
        expect($savedCount)->toBe(1);
        expect($service->itemExists('entry-guid-123', 'inbox'))->toBeTrue();

        // Second fetch of same item should deduplicate and return 0
        $secondSaved = $service->fetchSubscription('inbox', 'https://example.com/rss.xml', $rssContent);
        expect($secondSaved)->toBe(0);
    });

    it('saves ExtendedEntry directly via saveEntry', function () {
        $service = new FetchFeedsService();

        $entry = new ExtendedEntry();
        $entry->uid = 'unique-test-uid';
        $entry->url = 'https://example.org/entry';
        $entry->published = date('c');
        $entry->content['html'] = '<p>Custom entry</p>';
        $entry->author = ['name' => 'Tester', 'photo' => ''];

        $saved = $service->saveEntry($entry, 'notifications', 'https://example.org/feed');
        expect($saved)->toBeTrue();
        expect($service->itemExists('unique-test-uid', 'notifications'))->toBeTrue();
    });
});
