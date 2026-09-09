<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Indieinabox\Database;
use Indieinabox\Microsub\NormalizationAdapter;

class WebmentionDiscoveryIntegrationTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/test_discovery.sqlite';
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
        
        // Connect to test database and run migrations automatically
        Database::connect($this->dbPath);
    }

    protected function tearDown(): void
    {
        Database::disconnect();
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function testFromFeedQueuesUnknownDomain(): void
    {
        $db = Database::getDb();

        // Ensure table starts empty
        $stmt = $db->query('SELECT COUNT(*) FROM webmention_discovery_cache');
        $this->assertEquals(0, $stmt->fetchColumn());

        // Parse a feed from an unknown domain
        $entry = NormalizationAdapter::fromFeed('id1', 'https://unknown.com/post', '', time(), 'Author', 'https://unknown.com/rss');

        // It should default to local interactions
        $this->assertContains('local_like', $entry->capabilities);
        $this->assertNotContains('like', $entry->capabilities);

        // It should have queued the domain in the cache
        $stmt = $db->prepare('SELECT * FROM webmention_discovery_cache WHERE domain = ?');
        $stmt->execute(['unknown.com']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row);
        $this->assertEquals(0, $row['supports_webmention']);
        $this->assertEquals(0, $row['last_checked']);
    }

    public function testFromFeedRespectsCachedWebmentionSupport(): void
    {
        $db = Database::getDb();

        // Inject a known supported domain
        $stmt = $db->prepare('INSERT INTO webmention_discovery_cache (domain, supports_webmention, last_checked) VALUES (?, ?, ?)');
        $stmt->execute(['supported.com', 1, time()]);

        // Inject a known unsupported domain
        $stmt->execute(['unsupported.com', 0, time()]);

        // Test supported domain
        $entry1 = NormalizationAdapter::fromFeed('id1', 'https://supported.com/post', '', time(), 'Author', 'https://supported.com/rss');
        $this->assertContains('like', $entry1->capabilities);
        $this->assertNotContains('local_like', $entry1->capabilities);

        // Test unsupported domain
        $entry2 = NormalizationAdapter::fromFeed('id2', 'https://unsupported.com/post', '', time(), 'Author', 'https://unsupported.com/rss');
        $this->assertContains('local_like', $entry2->capabilities);
        $this->assertNotContains('like', $entry2->capabilities);
    }
}
