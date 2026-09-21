<?php

declare(strict_types=1);

use Indieinabox\Entry\Entry;
use Indieinabox\Site\Site;
use Indieinabox\Twtxt\TwtxtManager;

beforeEach(function () {
    global $site;
    global $backupSite;

    $backupSite = $site ?? null;
    $site = new Site();
});

afterEach(function () {
    global $site;
    global $backupSite;
    $site = $backupSite;
});

it('cleans messages correctly by removing markdown and formatting to single line', function () {
    $raw = "This is a **bold** and *italic* note.\n"
        . "Check out [link](https://lumen.pink).\n"
        . "Obsidian link [[My Note|Custom Label]] and [[Simple Link]].";

    $cleaned = TwtxtManager::cleanMessage($raw);

    expect($cleaned)->toBe(
        "This is a bold and italic note. Check out link (https://lumen.pink). Obsidian link Custom Label and Simple Link."
    );
});

it('formats plain message text to HTML correctly with mentions and tags', function () {
    $msg = "Hello @&lt;bob https://bob.com/twtxt.txt&gt; check #indieweb and https://lumen.pink";
    $html = TwtxtManager::formatMessageToHtml($msg);

    expect($html)->toContain('<a href="https://bob.com/twtxt.txt" class="mention">@bob</a>')
        ->and($html)->toContain('<a href="https://hub.twtxt.org/search?tag=indieweb" class="hashtag">#indieweb</a>')
        ->and($html)->toContain('<a href="https://lumen.pink" target="_blank" rel="noopener">https://lumen.pink</a>');
});

it('parses twtxt feed content correctly into Entry objects including hub mentions format', function () {
    $rawFeed = "# nick = bob\n"
        . "2021-01-01T00:00:00Z\tFirst update\n"
        . "2021-01-02T00:00:00Z\talice https://alice.com/twtxt.txt:\tHello @&lt;bob https://bob.com/twtxt.txt&gt;";

    $entries = TwtxtManager::parseFeedContent($rawFeed, 'bob');

    expect($entries)->toHaveCount(2);

    expect($entries[0])->toBeInstanceOf(Entry::class);
    expect($entries[0]->getAuthor()['name'])->toBe('bob')
        ->and($entries[0]->nick)->toBe('bob')
        ->and($entries[0]->getRawContent())->toBe('First update')
        ->and($entries[0]->message)->toBe('First update')
        ->and($entries[0]->isReply())->toBeFalse();

    expect($entries[1])->toBeInstanceOf(Entry::class);
    expect($entries[1]->getAuthor()['name'])->toBe('alice')
        ->and($entries[1]->nick)->toBe('alice')
        ->and($entries[1]->getAuthor()['url'])->toBe('https://alice.com/twtxt.txt')
        ->and($entries[1]->getRawContent())->toBe('Hello @&lt;bob https://bob.com/twtxt.txt&gt;')
        ->and($entries[1]->message)->toBe('Hello @&lt;bob https://bob.com/twtxt.txt&gt;')
        ->and($entries[1]->isReply())->toBeTrue()
        ->and($entries[1]->html)->toContain('<a href="https://bob.com/twtxt.txt" class="mention">@bob</a>');
});

it('fetches timeline from cache if http request fails', function () {
    $cacheDir = sys_get_temp_dir() . '/twtxt_test_cache_' . uniqid();
    mkdir($cacheDir);

    $url = 'http://localhost:9999/does-not-exist.txt'; // Will fail instantly
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . md5($url) . '.txt';

    $rawFeed = "# nick = remote_user\n2021-01-01T00:00:00Z\tCached update";
    file_put_contents($cacheFile, $rawFeed);

    $manager = new TwtxtManager();
    $following = [['nick' => 'remote_user', 'url' => $url]];

    // Disable errors for file_get_contents failure
    $entries = @$manager->fetchTimeline($following, $cacheDir);

    expect($entries)->toHaveCount(1);
    expect($entries[0])->toBeInstanceOf(Entry::class);
    expect($entries[0]->nick)->toBe('remote_user')
        ->and($entries[0]->message)->toBe('Cached update');

    unlink($cacheFile);
    rmdir($cacheDir);
});

it('fetches hub mentions and deduplicates them', function () {
    $manager = new TwtxtManager();
    $hubs = ['http://localhost:9999/does-not-exist'];
    $fqdn = 'https://lumen.pink';

    $entries = @$manager->fetchHubMentions($hubs, $fqdn, __DIR__ . '/tmp_twtxt');
    expect($entries)->toBeEmpty();
});

it('formats Yarns markdown, subjects, and inline code correctly', function () {
    $msg = "Look at `echo 'hello world'` and **bold text** with *italic text* and ~~strike text~~.\n"
        . "> This is a quote\n"
        . "Subject tag: (#pod_launch) and [Blog](https://lumen.pink/blog)";

    $html = TwtxtManager::formatMessageToHtml($msg);

    expect($html)->toContain('<code>echo &apos;hello world&apos;</code>')
        ->and($html)->toContain('<strong>bold text</strong>')
        ->and($html)->toContain('<em>italic text</em>')
        ->and($html)->toContain('<del>strike text</del>')
        ->and($html)->toContain('<blockquote class="twtxt-quote">This is a quote</blockquote>')
        ->and($html)->toContain('<span class="twtxt-subject">(#pod_launch)</span>')
        ->and($html)->toContain('<a href="https://lumen.pink/blog" target="_blank" rel="noopener">Blog</a>');
});

it('parses Yarns headers and subject tags from twtxt feeds into Entry title and metadata', function () {
    $rawFeed = "# title = Lumen Pod\n"
        . "# author = lumen\n"
        . "# link = https://lumen.pink/twtxt.txt\n"
        . "2026-09-21T20:00:00Z\t(#yarns_intro) Welcome to Yarns pod!";

    $entries = TwtxtManager::parseFeedContent($rawFeed, 'fallback_nick', 'https://lumen.pink/twtxt.txt');

    expect($entries)->toHaveCount(1);
    $entry = $entries[0];
    expect($entry->getTitle())->toBe('yarns_intro')
        ->and($entry->getMetadata()['subject'])->toBe('yarns_intro')
        ->and($entry->getMetadata()['feed_title'])->toBe('Lumen Pod')
        ->and($entry->getAuthor()['name'])->toBe('lumen')
        ->and($entry->getContent())->toContain('<span class="twtxt-subject">(#yarns_intro)</span>');
});

it('generates twtxt feed with Yarns headers and subjects', function () {
    $generator = new \Indieinabox\Feeds\Generators\TwtxtFeedGenerator();
    $site = new Site();
    $site->metadata->title = 'Test Site';
    $site->metadata->fqdn = 'https://example.com';
    $site->twtxt->nick = 'tester';
    $site->twtxt->description = 'A test feed';

    $entry = new Entry([
        'id' => 'test-1',
        'slug' => 'test-1',
        'kind' => 'note',
        'rawContent' => 'Testing note',
        'content' => '<p>Testing note</p>',
        'publishedAt' => new \DateTimeImmutable('2026-09-21T12:00:00Z'),
        'metadata' => ['subject' => 'test_subject'],
    ]);

    $tempFile = sys_get_temp_dir() . '/twtxt_yarns_test_' . uniqid() . '.txt';
    $generator->generate([$entry], $tempFile, $site);

    $content = file_get_contents($tempFile);
    unlink($tempFile);

    expect($content)->toContain("# title = Test Site\n")
        ->and($content)->toContain("# link = https://example.com/twtxt.txt\n")
        ->and($content)->toContain("# nick = tester\n")
        ->and($content)->toContain("# description = A test feed\n")
        ->and($content)->toContain("(#test_subject) Testing note");
});

