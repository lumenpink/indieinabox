<?php

declare(strict_types=1);

namespace Tests\Functional\Microsub;

use PHPUnit\Framework\TestCase;
use Indieinabox\Microsub\NormalizationAdapter;
use Indieinabox\Microsub\ExtendedEntry;
use Indieinabox\Database;
use Opis\JsonSchema\Validator;

/**
 * Functional tests that validate the full pipeline:
 *   Raw protocol data → NormalizationAdapter → ExtendedEntry → toJF2Array()
 * 
 * The JF2 output is validated against the formal JSON Schema to
 * guarantee that the live code always produces spec-compliant payloads.
 */
class NormalizationPipelineTest extends TestCase
{
    private Validator $validator;
    private \stdClass $schema;
    private string $dbPath;

    protected function setUp(): void
    {
        $this->validator = new Validator();
        $schemaPath = realpath(__DIR__ . '/../../../docs/api/extended_entry_schema.json');
        $this->schema = json_decode(file_get_contents($schemaPath));

        $this->dbPath = sys_get_temp_dir() . '/test_pipeline_' . uniqid() . '.sqlite';
        Database::connect($this->dbPath);
    }

    protected function tearDown(): void
    {
        Database::disconnect();
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function assertValidJF2(array $jf2): void
    {
        $data = json_decode(json_encode($jf2));
        $result = $this->validator->validate($data, $this->schema);

        if (!$result->isValid()) {
            $formatter = new \Opis\JsonSchema\Errors\ErrorFormatter();
            $errors = $formatter->format($result->error());
            $this->fail("toJF2Array() produced invalid schema output:\n" . json_encode($errors, JSON_PRETTY_PRINT));
        }

        $this->assertTrue(true);
    }

    public function testActivityPubPipelineProducesValidJF2(): void
    {
        $json = [
            'id' => 'https://mastodon.social/@alice/1001',
            'url' => 'https://mastodon.social/@alice/1001',
            'published' => '2026-09-09T12:00:00Z',
            'summary' => 'Spoiler: contains a cat opinion',
            'oneOf' => [
                ['name' => 'Cats', 'replies' => ['totalItems' => 42]],
                ['name' => 'Dogs', 'replies' => ['totalItems' => 17]],
            ]
        ];

        $entry = NormalizationAdapter::fromActivityPub($json, '<p>Which do you prefer?</p>');
        $jf2 = $entry->toJF2Array();

        $this->assertValidJF2($jf2);

        // Structural assertions
        $this->assertEquals('activitypub', $jf2['_indieinabox']['network']);
        $this->assertContains('poll_vote', $jf2['_indieinabox']['capabilities']);
        $this->assertEquals('Spoiler: contains a cat opinion', $jf2['_indieinabox']['content_warning']);
        $this->assertStringContainsString('cw-fallback', $jf2['content']['html']);
        $this->assertStringContainsString('poll-fallback', $jf2['content']['html']);
    }

    public function testTwtxtPipelineProducesValidJF2(): void
    {
        $entry = NormalizationAdapter::fromTwtxt(
            'twt-abc123',
            'https://bob.example.com/twtxt.txt',
            'Hello world! #twtxt https://example.com',
            time(),
            'Bob'
        );
        $jf2 = $entry->toJF2Array();

        $this->assertValidJF2($jf2);

        $this->assertEquals('twtxt', $jf2['_indieinabox']['network']);
        $this->assertEquals(['reply'], $jf2['_indieinabox']['capabilities']);
        $this->assertContains('twtxt', $jf2['category']);
        $this->assertStringContainsString('<a href="https://example.com">', $jf2['content']['html']);
    }

    public function testRssFeedPipelineProducesValidJF2(): void
    {
        // Pre-seed webmention cache so domain is known as supported
        $db = Database::getDb();
        $db->exec("INSERT INTO webmention_discovery_cache (domain, supports_webmention, last_checked) VALUES ('carol.blog', 1, " . time() . ")");

        $entry = NormalizationAdapter::fromFeed(
            'rss-xyz',
            'https://carol.blog/2026/post',
            '<h2>My Post</h2><p>About #indieweb.</p>',
            time(),
            'Carol',
            'https://carol.blog/feed.xml'
        );
        $jf2 = $entry->toJF2Array();

        $this->assertValidJF2($jf2);

        $this->assertEquals('rss', $jf2['_indieinabox']['network']);
        $this->assertContains('like', $jf2['_indieinabox']['capabilities']);
        $this->assertContains('indieweb', $jf2['category']);
    }

    public function testRssFeedWithoutWebmentionProducesLocalCapabilities(): void
    {
        // Pre-seed cache as not supporting webmention
        $db = Database::getDb();
        $db->exec("INSERT INTO webmention_discovery_cache (domain, supports_webmention, last_checked) VALUES ('old-blog.example.com', 0, " . time() . ")");

        $entry = NormalizationAdapter::fromFeed(
            'rss-old',
            'https://old-blog.example.com/post',
            '<p>Just a blog post.</p>',
            time(),
            'Dave',
            'https://old-blog.example.com/rss.xml'
        );
        $jf2 = $entry->toJF2Array();

        $this->assertValidJF2($jf2);

        $this->assertContains('local_like', $jf2['_indieinabox']['capabilities']);
        $this->assertNotContains('like', $jf2['_indieinabox']['capabilities']);
        $this->assertContains('repost', $jf2['_indieinabox']['capabilities']);
    }
}
