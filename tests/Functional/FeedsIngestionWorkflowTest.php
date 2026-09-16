<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Services\FetchFeedsService;
use Indieinabox\Services\MicrosubService;

$funcTempDir = __DIR__ . '/tmp_functional_feeds_workflow';

beforeEach(function () use ($funcTempDir) {
    if (!is_dir($funcTempDir)) {
        mkdir($funcTempDir, 0777, true);
    }

    Database::disconnect();
    $testDbPath = $funcTempDir . '/test.sqlite';
    if (file_exists($testDbPath)) {
        unlink($testDbPath);
    }
    Database::$dataDir = $funcTempDir . '/data';
    Database::connect($testDbPath);
    $db = Database::getDb();

    $schema = file_get_contents(__DIR__ . '/../../database.sql');
    $db->exec($schema);
});

afterEach(function () use ($funcTempDir) {
    Database::disconnect();
    Database::$dataDir = '';

    if (is_dir($funcTempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($funcTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($funcTempDir);
    }
});

describe('Feeds Ingestion Workflow', function () {
    it('executes full feed ingestion across multiple formats, timeline reading, and pruning', function () {
        $fetcher = new FetchFeedsService();
        $microsub = new class(null, $fetcher) extends MicrosubService {
            protected function fetchUrl(string $url, $context = null)
            {
                if (str_contains($url, 'rss.xml')) {
                    return '<?xml version="1.0"?><rss version="2.0"><channel><title>RSS Source</title></channel></rss>';
                }
                if (str_contains($url, 'feed.json')) {
                    return json_encode([
                        'version' => 'https://jsonfeed.org/version/1.1',
                        'title' => 'JSON Source',
                    ]);
                }
                if (str_contains($url, 'twtxt.txt')) {
                    return "# nick = twtxt_user\n2026-09-14T10:00:00Z\tInitial note";
                }
                return false;
            }
        };

        // 1. Create channel
        $chan = $microsub->createChannel('Engineering');
        $channelUid = $chan['uid'];
        expect($channelUid)->toBe('engineering');

        // 2. Follow three distinct feed types
        $microsub->follow($channelUid, 'https://feeds.test/rss.xml');
        $microsub->follow($channelUid, 'https://feeds.test/feed.json');
        $microsub->follow($channelUid, 'https://feeds.test/twtxt.txt');

        $subs = $microsub->getSubscriptions($channelUid);
        expect($subs)->toHaveCount(3);

        // 3. Ingest entries into channel
        $rssPayload = <<<XML
<?xml version="1.0"?>
<rss version="2.0">
    <channel>
        <title>RSS Source</title>
        <item>
            <title>Engineering Post 1</title>
            <guid>eng-post-1</guid>
            <link>https://feeds.test/eng-post-1</link>
            <pubDate>Mon, 14 Sep 2026 14:00:00 GMT</pubDate>
            <description>Exciting architecture update</description>
        </item>
    </channel>
</rss>
XML;
        $jsonPayload = json_encode([
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => 'JSON Source',
            'items' => [
                [
                    'id' => 'eng-json-1',
                    'url' => 'https://feeds.test/json-1',
                    'title' => 'JSON Post 1',
                    'content_text' => 'Fast JSON data update',
                    'date_published' => '2026-09-14T15:00:00Z',
                ],
            ],
        ]);
        $twtxtPayload = "# nick = twtxt_user\n2026-09-14T16:00:00Z\tTwtxt status message";

        $fetcher->fetchSubscription($channelUid, 'https://feeds.test/rss.xml', $rssPayload);
        $fetcher->fetchSubscription($channelUid, 'https://feeds.test/feed.json', $jsonPayload);
        $fetcher->fetchSubscription($channelUid, 'https://feeds.test/twtxt.txt', $twtxtPayload);

        // 4. Retrieve channel timeline
        $timeline = $microsub->getTimeline($channelUid);
        expect($timeline['items'])->toHaveCount(3);

        $ids = array_column($timeline['items'], '_id');
        expect($ids)->toContain('eng-post-1');
        expect($ids)->toContain('eng-json-1');

        // Check read state (initially unread)
        foreach ($timeline['items'] as $item) {
            expect($item['_is_read'])->toBeFalse();
        }

        // 5. Mark eng-post-1 as read
        $microsub->markRead($channelUid, ['eng-post-1']);
        $timelineAfterRead = $microsub->getTimeline($channelUid);
        foreach ($timelineAfterRead['items'] as $item) {
            if ($item['_id'] === 'eng-post-1') {
                expect($item['_is_read'])->toBeTrue();
            } else {
                expect($item['_is_read'])->toBeFalse();
            }
        }

        // 6. Unfollow JSON feed and verify its stored entries are pruned
        $unfollow = $microsub->unfollow($channelUid, 'https://feeds.test/feed.json');
        expect($unfollow)->toBeTrue();

        $timelineAfterUnfollow = $microsub->getTimeline($channelUid);
        expect($timelineAfterUnfollow['items'])->toHaveCount(2);
        $remainingIds = array_column($timelineAfterUnfollow['items'], '_id');
        expect($remainingIds)->not->toContain('eng-json-1');
    });
});
