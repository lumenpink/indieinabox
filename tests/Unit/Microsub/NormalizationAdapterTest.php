<?php

declare(strict_types=1);

namespace Tests\Unit\Microsub;

use PHPUnit\Framework\TestCase;
use Indieinabox\Microsub\NormalizationAdapter;
use Indieinabox\Microsub\ExtendedEntry;

class NormalizationAdapterTest extends TestCase
{
    public function testFromActivityPubWithCwAndPoll(): void
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

        $entry = NormalizationAdapter::fromActivityPub($json, '<p>Pick one.</p>');

        $this->assertInstanceOf(ExtendedEntry::class, $entry);
        $this->assertEquals('activitypub', $entry->network);
        $this->assertEquals('mastodon.social', $entry->originServer);
        $this->assertEquals('Spoiler Alert', $entry->contentWarning);

        $this->assertContains('reply', $entry->capabilities);
        $this->assertContains('like', $entry->capabilities);
        $this->assertContains('poll_vote', $entry->capabilities);

        $this->assertNotNull($entry->poll);
        $this->assertFalse($entry->poll['multiple_choice']); // oneOf = single choice
        $this->assertCount(2, $entry->poll['options']);
        $this->assertEquals(10, $entry->poll['options'][1]['votes']);
    }

    public function testFromActivityPubMultipleChoicePoll(): void
    {
        $json = [
            'id' => 'https://mastodon.social/@test/456',
            'url' => 'https://mastodon.social/@test/456',
            'anyOf' => [ // anyOf = multiple choice
                ['name' => 'Cat', 'replies' => ['totalItems' => 3]],
                ['name' => 'Dog', 'replies' => ['totalItems' => 7]]
            ]
        ];

        $entry = NormalizationAdapter::fromActivityPub($json, '<p>Pick all you like.</p>');

        $this->assertTrue($entry->poll['multiple_choice']);
    }

    public function testFromActivityPubWithoutCwOrPoll(): void
    {
        $json = [
            'id' => 'https://mastodon.social/@test/789',
            'url' => 'https://mastodon.social/@test/789',
            'published' => '2026-09-09T10:00:00Z',
        ];

        $entry = NormalizationAdapter::fromActivityPub($json, '<p>Plain post.</p>');

        $this->assertNull($entry->contentWarning);
        $this->assertNull($entry->poll);
        $this->assertNotContains('poll_vote', $entry->capabilities);
    }

    public function testFromTwtxtExtractsHashtags(): void
    {
        $text = 'Hello #twtxt world! Check this out https://example.com/cool #indieweb';

        $entry = NormalizationAdapter::fromTwtxt('uid-1', 'https://bob.example.com/twtxt.txt', $text, time(), 'Bob');

        $this->assertContains('twtxt', $entry->category);
        $this->assertContains('indieweb', $entry->category);
    }

    public function testFromTwtxtCapabilitiesAreReplyOnly(): void
    {
        $entry = NormalizationAdapter::fromTwtxt('uid-2', 'https://bob.example.com/twtxt.txt', 'Hello', time(), 'Bob');

        $this->assertEquals(['reply'], $entry->capabilities);
    }

    public function testFromTwtxtLinksAreConvertedToHtml(): void
    {
        $text = 'Check this: https://example.com/link';
        $entry = NormalizationAdapter::fromTwtxt('uid-3', 'https://example.com/twtxt.txt', $text, time(), 'Alice');

        $this->assertStringContainsString('<a href="https://example.com/link">', $entry->content['html']);
    }
}
