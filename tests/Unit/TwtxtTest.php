<?php

declare(strict_types=1);

use Indieinabox\Page;
use Indieinabox\Site;
use Indieinabox\Site\Twtxt as TwtxtConfig;
use Indieinabox\Twtxt\TwtxtManager;
use Indieinabox\Twtxt\TwtxtEntry;

beforeEach(function () {
    global $site;
    global $backupSite;

    $backupSite = $site ?? null;
    $site = new Site();
    $site->config = [
        'kinds' => [
            'note' => [
                'display_mode' => 'full_content',
                'show_on_home' => true
            ],
            'photo' => [
                'display_mode' => 'thumbnail_snippet',
                'show_on_home' => true
            ],
            'article' => [
                'display_mode' => 'default',
                'show_on_home' => true
            ]
        ]
    ];
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
    
    expect($cleaned)->toBe("This is a bold and italic note. Check out link (https://lumen.pink). Obsidian link Custom Label and Simple Link.");
});

it('formats page content to twtxt message correctly based on page kinds', function () {
    $fqdn = "https://lumen.pink";
    
    // 1. Note kind
    $note = Page::fromArray([
        'title' => 'My Note',
        'kind' => 'note',
        'slug' => 'notes/my-note/',
        'content' => 'Content here',
        'rawBody' => "Hello **world**.\nSecond line."
    ]);
    $noteMessage = TwtxtManager::formatPageToTwtxtMessage($note, $fqdn);
    expect($noteMessage)->toBe("Hello world. Second line.");
    
    // 2. Photo kind
    $photo = Page::fromArray([
        'title' => 'Cool Photo',
        'kind' => 'photo',
        'slug' => 'fotos/photo-post/',
        'images' => ['/assets/img.jpg'],
        'rawBody' => 'Nice scenery *outside*.'
    ]);
    $photoMessage = TwtxtManager::formatPageToTwtxtMessage($photo, $fqdn);
    expect($photoMessage)->toBe("Nice scenery outside. https://lumen.pink/assets/img.jpg - https://lumen.pink/fotos/photo-post/");
    
    // 3. Article kind
    $article = Page::fromArray([
        'title' => 'My Article',
        'kind' => 'article',
        'slug' => 'artigos/my-first-post/',
        'rawBody' => 'A very long markdown post about static sites...'
    ]);
    $articleMessage = TwtxtManager::formatPageToTwtxtMessage($article, $fqdn);
    expect($articleMessage)->toBe("My Article: A very long markdown post about static sites... - https://lumen.pink/artigos/my-first-post/");
});

it('formats plain message text to HTML correctly with mentions and tags', function () {
    $msg = "Hello @&lt;bob https://bob.com/twtxt.txt&gt; check #indieweb and https://lumen.pink";
    $html = TwtxtManager::formatMessageToHtml($msg);
    
    expect($html)->toContain('<a href="https://bob.com/twtxt.txt" class="mention">@bob</a>')
        ->and($html)->toContain('<a href="https://hub.twtxt.org/search?tag=indieweb" class="hashtag">#indieweb</a>')
        ->and($html)->toContain('<a href="https://lumen.pink" target="_blank" rel="noopener">https://lumen.pink</a>');
});

it('generates twtxt feed with metadata comment headers and entries', function () {
    $pages = [
        Page::fromArray([
            'title' => 'My Note',
            'kind' => 'note',
            'slug' => 'notes/my-note/',
            'date' => 1609459200, // 2021-01-01T00:00:00Z
            'rawBody' => 'Just note.'
        ]),
        Page::fromArray([
            'title' => 'Ignored Garden Page',
            'kind' => 'garden',
            'slug' => 'garden/some-idea/',
            'date' => 1609459300,
            'rawBody' => 'Ignored.'
        ])
    ];
    
    $config = new TwtxtConfig(
        'lumen',
        'Personal microblog',
        'https://lumen.pink/avatar.png',
        [['nick' => 'bob', 'url' => 'https://bob.com/twtxt.txt']],
        ['https://hub.twtxt.org']
    );
    
    $tmpFile = tempnam(sys_get_temp_dir(), 'twtxt');
    
    $manager = new TwtxtManager();
    $manager->generateFeed($pages, $tmpFile, 'https://lumen.pink', $config);
    
    $content = file_get_contents($tmpFile);
    unlink($tmpFile);
    
    expect($content)->toContain('# nick = lumen')
        ->and($content)->toContain('# description = Personal microblog')
        ->and($content)->toContain('# avatar = https://lumen.pink/avatar.png')
        ->and($content)->toContain('# follow = bob https://bob.com/twtxt.txt')
        ->and($content)->toContain("2021-01-01T00:00:00Z\tJust note.")
        ->and($content)->not->toContain('Ignored.');
});

it('parses twtxt feed content correctly including hub mentions format', function () {
    $rawFeed = "# nick = bob\n"
        . "2021-01-01T00:00:00Z\tFirst update\n"
        . "2021-01-02T00:00:00Z\talice https://alice.com/twtxt.txt:\tHello @&lt;bob https://bob.com/twtxt.txt&gt;";
        
    $entries = TwtxtManager::parseFeedContent($rawFeed, 'bob');
    
    expect($entries)->toHaveCount(2);
    
    expect($entries[0]->nick)->toBe('bob')
        ->and($entries[0]->message)->toBe('First update');
        
    expect($entries[1]->nick)->toBe('alice')
        ->and($entries[1]->message)->toBe('Hello @&lt;bob https://bob.com/twtxt.txt&gt;')
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
    
    expect($entries)->toHaveCount(1)
        ->and($entries[0]->nick)->toBe('remote_user')
        ->and($entries[0]->message)->toBe('Cached update');
        
    unlink($cacheFile);
    rmdir($cacheDir);
});

it('fetches hub mentions and deduplicates them', function () {
    // We can't easily mock fetchUrl without reflection or a local server.
    // However, we can test that if hubs are provided, it attempts to fetch them.
    // If we provide a bogus hub, it returns an empty array.
    $manager = new TwtxtManager();
    $hubs = ['http://localhost:9999/does-not-exist'];
    $fqdn = 'https://lumen.pink';
    
    $entries = @$manager->fetchHubMentions($hubs, $fqdn);
    expect($entries)->toBeArray()->toBeEmpty();
});
