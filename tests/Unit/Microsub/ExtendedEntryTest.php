<?php

declare(strict_types=1);

namespace Tests\Unit\Microsub;

use PHPUnit\Framework\TestCase;
use Indieinabox\Microsub\ExtendedEntry;

class ExtendedEntryTest extends TestCase
{
    public function testToJF2ArrayGeneratesCorrectStructure(): void
    {
        $entry = new ExtendedEntry();
        $entry->uid = 'post-1';
        $entry->url = 'https://example.com/post-1';
        $entry->published = '2026-09-08T21:00:00Z';
        $entry->content = ['html' => '<p>Hello</p>', 'text' => 'Hello'];
        $entry->author = ['type' => 'card', 'name' => 'Charlie'];
        
        $entry->network = 'activitypub';
        $entry->originServer = 'mastodon.social';
        $entry->capabilities = ['reply', 'like'];

        $arr = $entry->toJF2Array();

        $this->assertEquals('entry', $arr['type']);
        $this->assertEquals('post-1', $arr['uid']);
        $this->assertEquals('https://example.com/post-1', $arr['url']);
        $this->assertEquals('Charlie', $arr['author']['name']);
        
        // Assert _indieinabox metadata
        $this->assertArrayHasKey('_indieinabox', $arr);
        $this->assertEquals('activitypub', $arr['_indieinabox']['network']);
        $this->assertEquals('mastodon.social', $arr['_indieinabox']['origin_server']);
        $this->assertContains('like', $arr['_indieinabox']['capabilities']);
    }

    public function testGracefulDegradationForContentWarning(): void
    {
        $entry = new ExtendedEntry();
        $entry->content = ['html' => '<p>Secret</p>', 'text' => 'Secret'];
        $entry->contentWarning = 'Spoiler';

        $arr = $entry->toJF2Array();

        $html = $arr['content']['html'];
        $text = $arr['content']['text'];

        $this->assertStringContainsString('<div class="cw-fallback"><details><summary>CW: Spoiler</summary>', $html);
        $this->assertStringContainsString('<p>Secret</p>', $html);
        $this->assertStringStartsWith('CW: Spoiler. Secret', $text);
    }

    public function testGracefulDegradationForPolls(): void
    {
        $entry = new ExtendedEntry();
        $entry->content = ['html' => '<p>Vote now!</p>', 'text' => 'Vote now!'];
        $entry->poll = [
            'options' => [
                ['title' => 'Yes', 'votes' => 10],
                ['title' => 'No', 'votes' => 5]
            ]
        ];

        $arr = $entry->toJF2Array();
        $html = $arr['content']['html'];

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Yes (10 votes)</li>', $html);
        $this->assertStringContainsString('<li>No (5 votes)</li>', $html);
        
        $this->assertNotNull($arr['_indieinabox']['poll']);
    }
}
