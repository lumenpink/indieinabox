<?php

declare(strict_types=1);

use Indieinabox\Database;
use Indieinabox\Services\MicrosubService;
use Indieinabox\Services\FetchFeedsService;

$tempDir = __DIR__ . '/tmp_microsub_unit';

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

describe('MicrosubService', function () {
    it('manages channel creation and listing', function () {
        $service = new MicrosubService();
        $channels = $service->getChannels();
        expect($channels)->toBeArray();
        expect(count($channels))->toBeGreaterThanOrEqual(2); // inbox, notifications from schema

        $newChan = $service->createChannel('Tech News');
        expect($newChan['name'])->toBe('Tech News');
        expect($newChan['uid'])->toBe('technews');

        $updated = $service->getChannels();
        $uids = array_column($updated, 'uid');
        expect($uids)->toContain('technews');

        // Can delete custom channel
        $deleted = $service->deleteChannel('technews');
        expect($deleted)->toBeTrue();

        // Cannot delete default channel
        expect(fn () => $service->deleteChannel('inbox'))->toThrow(Exception::class);
    });

    it('manages timeline entries and marks items as read', function () {
        $fetchService = new FetchFeedsService();
        $rss = <<<XML
<?xml version="1.0"?>
<rss version="2.0">
    <channel>
        <title>Blog</title>
        <item>
            <title>Post Alpha</title>
            <guid>alpha-1</guid>
            <link>https://blog.test/1</link>
            <description>Content Alpha</description>
        </item>
    </channel>
</rss>
XML;
        $fetchService->fetchSubscription('inbox', 'https://blog.test/rss', $rss);

        $service = new MicrosubService(null, $fetchService);
        $timeline = $service->getTimeline('inbox');
        expect($timeline)->toHaveKey('items');
        expect($timeline['items'])->toHaveCount(1);
        expect($timeline['items'][0]['_id'])->toBe('alpha-1');
        expect($timeline['items'][0]['_is_read'])->toBeFalse();

        // Mark read
        $res = $service->markRead('inbox', ['alpha-1']);
        expect($res)->toBeTrue();

        $timelineAfter = $service->getTimeline('inbox');
        expect($timelineAfter['items'][0]['_is_read'])->toBeTrue();
    });

    it('searches for feed links in html content', function () {
        $service = new class extends MicrosubService {
            protected function fetchUrl(string $url, $context = null)
            {
                return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <link rel="alternate" type="application/rss+xml" href="https://example.org/feed.xml">
    <link rel="alternate" type="application/feed+json" href="/feed.json">
</head>
<body><h1>Hello</h1></body>
</html>
HTML;
            }
        };

        $results = $service->search('https://example.org');
        expect($results)->toHaveCount(2);
        expect($results[0]['url'])->toBe('https://example.org/feed.xml');
        expect($results[1]['url'])->toBe('https://example.org/feed.json');
    });

    it('follows and unfollows feeds updating database subscriptions', function () {
        $service = new class extends MicrosubService {
            protected function fetchUrl(string $url, $context = null)
            {
                if (str_contains($url, 'rss.xml')) {
                    return '<?xml version="1.0"?><rss version="2.0"><channel><title>Followed Feed</title></channel></rss>';
                }
                return false;
            }
        };

        $sub = $service->follow('inbox', 'https://site.org/rss.xml');
        expect($sub['type'])->toBe('feed');
        expect($sub['feed_type'])->toBe('rss');
        expect($sub['name'])->toBe('Followed Feed');

        $subs = $service->getSubscriptions('inbox');
        expect($subs)->toHaveCount(1);
        expect($subs[0]['url'])->toBe('https://site.org/rss.xml');

        $unfollow = $service->unfollow('inbox', 'https://site.org/rss.xml');
        expect($unfollow)->toBeTrue();

        $subsAfter = $service->getSubscriptions('inbox');
        expect($subsAfter)->toBeEmpty();
    });
});
