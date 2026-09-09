<?php

declare(strict_types=1);

namespace Tests\Unit\Microsub;

use PHPUnit\Framework\TestCase;
use Indieinabox\Microsub\NormalizationAdapter;
use Indieinabox\Microsub\ExtendedEntry;

class NormalizationAdapterTest extends TestCase
{
    public function testFromActivityPub(): void
    {
        $json = [
            'id' => 'https://mastodon.social/@test/123',
            'url' => 'https://mastodon.social/@test/123',
            'published' => '2026-09-08T21:00:00Z',
            'summary' => 'Spoiler Alert',
            'oneOf' => [
                ['name' => 'Option A', 'replies' => ['totalItems' => 5]],
                ['name' => 'Option B', 'replies' => ['totalItems' => 10]]
            ]
        ];
        
        $html = '<p>This is a test post.</p>';

        $entry = NormalizationAdapter::fromActivityPub($json, $html);

        $this->assertInstanceOf(ExtendedEntry::class, $entry);
        $this->assertEquals('activitypub', $entry->network);
        $this->assertEquals('mastodon.social', $entry->originServer);
        $this->assertEquals('Spoiler Alert', $entry->contentWarning);
        
        $this->assertContains('reply', $entry->capabilities);
        $this->assertContains('like', $entry->capabilities);
        $this->assertContains('poll_vote', $entry->capabilities);

        $this->assertNotNull($entry->poll);
        $this->assertFalse($entry->poll['multiple_choice']);
        $this->assertCount(2, $entry->poll['options']);
        $this->assertEquals(10, $entry->poll['options'][1]['votes']);
        
        $this->assertEquals($html, $entry->content['html']);
    }

    public function testFromTwtxt(): void
    {
        $uid = 'twtxt-123';
        $url = 'https://example.com/twtxt.txt';
        $text = 'Hello twtxt world! https://example.com/link';
        $timestamp = 1694200000;
        $authorName = 'Alice';

        $entry = NormalizationAdapter::fromTwtxt($uid, $url, $text, $timestamp, $authorName);

        $this->assertInstanceOf(ExtendedEntry::class, $entry);
        $this->assertEquals('twtxt', $entry->network);
        $this->assertEquals('example.com', $entry->originServer);
        $this->assertEquals(['reply'], $entry->capabilities);
        
        $this->assertEquals($text, $entry->content['text']);
        $this->assertStringContainsString('<a href="https://example.com/link">', $entry->content['html']);
        
        $this->assertNotNull($entry->author);
        $this->assertEquals('Alice', $entry->author['name']);
    }

    public function testFromFeed(): void
    {
        $uid = 'rss-123';
        $url = 'https://blog.example.com/post-1';
        $html = '<h1>Feed Post</h1>';
        $timestamp = 1694200000;
        $authorName = 'Bob';
        $feedUrl = 'https://blog.example.com/feed.xml';

        $entry = NormalizationAdapter::fromFeed($uid, $url, $html, $timestamp, $authorName, $feedUrl);

        $this->assertInstanceOf(ExtendedEntry::class, $entry);
        $this->assertEquals('rss', $entry->network);
        $this->assertEquals('blog.example.com', $entry->originServer);
        $this->assertEquals(['reply', 'like', 'repost'], $entry->capabilities);
        
        $this->assertEquals($html, $entry->content['html']);
        $this->assertEquals('Feed Post', $entry->content['text']);
        
        $this->assertNotNull($entry->author);
        $this->assertEquals('Bob', $entry->author['name']);
    }
}
