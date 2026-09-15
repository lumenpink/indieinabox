<?php

declare(strict_types=1);

use Indieinabox\Feeds\Parsers\TwtxtParser;
use Indieinabox\Feeds\Parsers\RssParser;
use Indieinabox\Feeds\Parsers\AtomParser;
use Indieinabox\Feeds\Parsers\JsonFeedParser;

describe('FeedParsers Strategies', function () {
    it('parses twtxt feeds correctly', function () {
        $parser = new TwtxtParser();
        expect($parser->getFormat())->toBe('twtxt');

        $content = "# nick = alice\n# url = https://example.com/twtxt.txt\n2026-09-14T20:00:00Z\tHello twtxt world!";
        expect($parser->supports($content))->toBeTrue();
        expect($parser->supports('<html></html>'))->toBeFalse();

        $items = $parser->parse($content, 'https://example.com/twtxt.txt');
        expect($items)->toHaveCount(1);
        expect($items[0]['content'])->toBe('Hello twtxt world!');
        expect($items[0]['author']['name'])->toBe('alice');
        expect($items[0]['url'])->toBe('https://example.com/twtxt.txt');
    });

    it('parses RSS feeds correctly', function () {
        $parser = new RssParser();
        expect($parser->getFormat())->toBe('rss');

        $rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Tech Blog</title>
        <link>https://blog.example.com</link>
        <item>
            <title>First Post</title>
            <link>https://blog.example.com/posts/1</link>
            <guid>post-1</guid>
            <pubDate>Mon, 14 Sep 2026 12:00:00 GMT</pubDate>
            <description><![CDATA[<p>Article body</p>]]></description>
        </item>
    </channel>
</rss>
XML;

        expect($parser->supports($rss))->toBeTrue();
        expect($parser->supports('{"version":"1.0"}'))->toBeFalse();

        $items = $parser->parse($rss, 'https://blog.example.com/rss.xml');
        expect($items)->toHaveCount(1);
        expect($items[0]['uid'])->toBe('post-1');
        expect($items[0]['title'])->toBe('First Post');
        expect($items[0]['url'])->toBe('https://blog.example.com/posts/1');
        expect($items[0]['content'])->toContain('Article body');
        expect($items[0]['author']['name'])->toBe('Tech Blog');
    });

    it('parses Atom feeds correctly', function () {
        $parser = new AtomParser();
        expect($parser->getFormat())->toBe('atom');

        $atom = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Personal Feed</title>
    <icon>https://feed.example.com/icon.png</icon>
    <entry>
        <id>urn:uuid:12345</id>
        <title>Atom Post</title>
        <link rel="alternate" href="https://feed.example.com/entry/1"/>
        <updated>2026-09-14T15:30:00Z</updated>
        <content type="html"><![CDATA[<p>Atom content</p>]]></content>
        <author>
            <name>Bob</name>
        </author>
    </entry>
</feed>
XML;

        expect($parser->supports($atom))->toBeTrue();
        expect($parser->supports('Not XML'))->toBeFalse();

        $items = $parser->parse($atom, 'https://feed.example.com/atom.xml');
        expect($items)->toHaveCount(1);
        expect($items[0]['uid'])->toBe('urn:uuid:12345');
        expect($items[0]['title'])->toBe('Atom Post');
        expect($items[0]['url'])->toBe('https://feed.example.com/entry/1');
        expect($items[0]['content'])->toBe('<p>Atom content</p>');
        expect($items[0]['author']['name'])->toBe('Bob');
        expect($items[0]['author']['photo'])->toBe('https://feed.example.com/icon.png');
    });

    it('parses JSON Feed format correctly', function () {
        $parser = new JsonFeedParser();
        expect($parser->getFormat())->toBe('jsonfeed');

        $jsonFeed = json_encode([
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => 'My JSON Feed',
            'icon' => 'https://json.example.com/avatar.jpg',
            'items' => [
                [
                    'id' => 'json-post-1',
                    'url' => 'https://json.example.com/p1',
                    'title' => 'First JSON Item',
                    'content_html' => '<p>Hello from JSON Feed</p>',
                    'date_published' => '2026-09-14T18:00:00Z',
                    'author' => [
                        'name' => 'Carol',
                        'avatar' => 'https://json.example.com/carol.png',
                    ],
                ],
            ],
        ]);

        expect($parser->supports($jsonFeed))->toBeTrue();
        expect($parser->supports('{"random":"json"}'))->toBeFalse();

        $items = $parser->parse($jsonFeed, 'https://json.example.com/feed.json');
        expect($items)->toHaveCount(1);
        expect($items[0]['uid'])->toBe('json-post-1');
        expect($items[0]['title'])->toBe('First JSON Item');
        expect($items[0]['url'])->toBe('https://json.example.com/p1');
        expect($items[0]['content'])->toBe('<p>Hello from JSON Feed</p>');
        expect($items[0]['author']['name'])->toBe('Carol');
        expect($items[0]['author']['photo'])->toBe('https://json.example.com/carol.png');
    });
});
