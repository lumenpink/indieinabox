<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Discovery\DiscoveryService;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_discovery_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/content', 0777, true);
    mkdir($this->tempDir . '/data/microsub/inbox/timeline', 0777, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir . '/data';
    Database::connect(':memory:');
    $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    Database::getDb()->exec($sql);

    $paths = new Paths($this->tempDir);
    $paths->contentDir = $this->tempDir . '/content';
    $this->site = new Site(null, $paths);
});

afterEach(function () {
    Database::disconnect();
    FileUtils::recursiveRmdir($this->tempDir);
});

it('aggregates federated and local public posts correctly', function () {
    // 1. Create a local markdown post
    $localPost = <<<'MD'
---
title: My First Local Post
date: 2026-09-20 12:00:00
tags:
  - web
  - indieweb
---
Hello world from the indieweb! #decentralization
MD;
    file_put_contents($this->tempDir . '/content/first-post.md', $localPost);

    // 2. Create a federated ActivityPub post in Microsub inbox
    $apPost = <<<'MD'
---
id: ap_12345
author_name: Alice
author_url: https://mastodon.social/@alice
published: 1789900000
_indieinabox:
  network: activitypub
  origin_server: mastodon.social
---
<p>Hello from Mastodon! #federation</p>
MD;
    file_put_contents($this->tempDir . '/data/microsub/inbox/timeline/ap_12345.md', $apPost);

    // 3. Create a federated Twtxt post
    $twtxtPost = <<<'MD'
---
id: twtxt_999
author_name: Bob
published: 1789800000
_indieinabox:
  network: twtxt
  origin_server: yarns.club
---
Eating ramen #food
MD;
    file_put_contents($this->tempDir . '/data/microsub/inbox/timeline/twtxt_999.md', $twtxtPost);

    $service = new DiscoveryService(Database::getDb(), $this->site);
    $result = $service->getTimeline();

    expect($result['total'])->toBe(3);
    expect(count($result['items']))->toBe(3);

    // Should contain counts for networks
    expect($result['networks'])->toHaveKey('activitypub');
    expect($result['networks'])->toHaveKey('twtxt');
    expect($result['networks'])->toHaveKey('local');

    // Should extract tags from both frontmatter and inline hashtags
    expect($result['tags'])->toHaveKey('web');
    expect($result['tags'])->toHaveKey('federation');
    expect($result['tags'])->toHaveKey('food');
});

it('filters entries by network and tag', function () {
    $apPost = <<<'MD'
---
id: ap_item
author_name: Charlie
published: 1789900000
_indieinabox:
  network: activitypub
---
<p>Testing #tag1</p>
MD;
    file_put_contents($this->tempDir . '/data/microsub/inbox/timeline/ap.md', $apPost);

    $rssPost = <<<'MD'
---
id: rss_item
author_name: Dave
published: 1789800000
_indieinabox:
  network: rss
---
<p>Testing #tag2</p>
MD;
    file_put_contents($this->tempDir . '/data/microsub/inbox/timeline/rss.md', $rssPost);

    $service = new DiscoveryService(Database::getDb(), $this->site);

    // Filter by network
    $apOnly = $service->getTimeline(['network' => 'activitypub']);
    expect($apOnly['total'])->toBe(1);
    expect($apOnly['items'][0]['id'])->toBe('ap_item');

    // Filter by tag
    $tag2Only = $service->getTimeline(['tag' => 'tag2']);
    expect($tag2Only['total'])->toBe(1);
    expect($tag2Only['items'][0]['id'])->toBe('rss_item');
});

it('excludes private, draft and unlisted posts from discovery', function () {
    $privatePost = <<<'MD'
---
title: Secret Notes
visibility: private
---
Should not be visible.
MD;
    file_put_contents($this->tempDir . '/content/secret.md', $privatePost);

    $draftPost = <<<'MD'
---
title: Draft Post
visibility: draft
---
Draft content.
MD;
    file_put_contents($this->tempDir . '/content/draft.md', $draftPost);

    $service = new DiscoveryService(Database::getDb(), $this->site);
    $result = $service->getTimeline();

    expect($result['total'])->toBe(0);
    expect($result['items'])->toBeEmpty();
});

it('utilizes cache and supports cache clearance', function () {
    $post = <<<'MD'
---
title: Cached Post
date: 2026-09-21
---
Body text.
MD;
    file_put_contents($this->tempDir . '/content/post.md', $post);

    $service = new DiscoveryService(Database::getDb(), $this->site, null, null, null, 120);
    $res1 = $service->getTimeline();
    expect($res1['total'])->toBe(1);

    // Add another post to disk
    $post2 = <<<'MD'
---
title: Second Post
date: 2026-09-22
---
Second body.
MD;
    file_put_contents($this->tempDir . '/content/post2.md', $post2);

    // Calling again should return cached result (still 1 item)
    $res2 = $service->getTimeline();
    expect($res2['total'])->toBe(1);

    // After clearing cache, it must refresh from disk (now 2 items)
    $service->clearCache();
    $res3 = $service->getTimeline();
    expect($res3['total'])->toBe(2);
});
