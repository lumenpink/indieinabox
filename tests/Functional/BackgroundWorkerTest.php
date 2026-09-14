<?php

declare(strict_types=1);

use Indieinabox\BackgroundWorker;
use Indieinabox\BackgroundWorker\ArchiveProcessor;
use Indieinabox\BackgroundWorker\InboxProcessor;
use Indieinabox\Database;
use Indieinabox\Helper;
use Indieinabox\Site;

$funcTempDir = __DIR__ . '/tmp_functional_bgworker';

beforeEach(function () use ($funcTempDir) {
    if (!is_dir($funcTempDir)) {
        mkdir($funcTempDir, 0777, true);
    }
    
    // Set up test database
    Database::disconnect();
    
    $testDbPath = $funcTempDir . '/test.sqlite';
    if (file_exists($testDbPath)) {
        unlink($testDbPath);
    }
    
    Database::$dataDir = $funcTempDir;
    Database::connect($testDbPath);
    $db = Database::getDb();
    
    // Clear inbox
    $inboxDir = $funcTempDir . '/microsub/inbox/inbox';
    if (is_dir($inboxDir)) {
        Helper::recursiveRmdir($inboxDir);
    }
    mkdir($inboxDir, 0777, true);
    
    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/../../database.sql');
    $db->exec($schema);
    
    $paths = new \Indieinabox\Site\Paths(
        $funcTempDir,
        $funcTempDir . '/public_html',
        $funcTempDir . '/public_gemini',
        $funcTempDir . '/public_gopher',
        $funcTempDir . '/public_media',
        $funcTempDir . '/content',
        $funcTempDir . '/resources'
    );
    $this->site = new Site(null, $paths);
    $GLOBALS['site'] = $this->site;
});

afterEach(function () {
    Database::disconnect();
});

it('processes archive queue and saves to db', function () {
    $db = Database::getDb();
    $url = 'https://example.com/post/1';
    $db->exec("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES ('$url', " . time() . ", 0, 'pending')");
    
    $calledArchiveOrg = [];
    $calledMicrolink = [];

    $archiveOrgSender = function (string $u) use (&$calledArchiveOrg) {
        $calledArchiveOrg[] = $u;
    };
    $pdfFetcher = function (string $u, string $normUrl, string $pdfDir) use (&$calledMicrolink) {
        $calledMicrolink[] = $u;
        $filename = md5($normUrl . time()) . '.pdf';
        $filepath = $pdfDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($filepath, 'dummy pdf data');
        return '/data/archives/dummy.pdf';
    };

    $urlResolver = fn(string $u) => $u;
    $processor = new ArchiveProcessor($this->site, $db, $urlResolver, $archiveOrgSender, $pdfFetcher);
    $processor->process();
    
    expect($calledArchiveOrg)->toContain($url);
    expect($calledMicrolink)->toContain($url);
    
    // Check queue is empty
    $stmt = $db->query("SELECT * FROM archive_queue");
    expect($stmt->fetchAll())->toBeEmpty();
    
    // Check archived_links
    $stmt = $db->query("SELECT * FROM archived_links");
    $links = $stmt->fetchAll();
    expect($links)->toHaveCount(1);
    expect($links[0]['url'])->toBe('https://example.com/post/1');
    expect($links[0]['local_pdf_path'])->toBe('/data/archives/dummy.pdf');
});

it('skips archiving if already archived within 24h unless force_archive is set', function () {
    $db = Database::getDb();
    $url = 'https://example.com/post/2';
    $db->exec("INSERT INTO archived_links (url, timestamp, local_pdf_path, archive_org_url) VALUES ('$url', " . time() . ", null, null)");
    
    $calledArchiveOrg = [];
    $archiveOrgSender = function (string $u) use (&$calledArchiveOrg) {
        $calledArchiveOrg[] = $u;
    };
    $urlResolver = fn(string $u) => $u;

    $processor = new ArchiveProcessor($this->site, $db, $urlResolver, $archiveOrgSender);

    // Regular request (should skip)
    $db->exec("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES ('$url', " . time() . ", 0, 'pending')");
    $processor->process();
    expect($calledArchiveOrg)->toBeEmpty();
    
    // Force request (should process)
    $db->exec("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES ('$url', " . time() . ", 1, 'pending')");
    $processor->process();
    expect($calledArchiveOrg)->toContain($url);
});

it('cannot run concurrently due to flock', function () use ($funcTempDir) {
    // Open a lock manually
    $lockFile = $funcTempDir . '/cron.lock';
    $fp = fopen($lockFile, 'w+');
    flock($fp, LOCK_EX | LOCK_NB);
    
    $worker = new BackgroundWorker($this->site);
    ob_start();
    $worker->runAll();
    $output = ob_get_clean();
    
    expect($output)->toContain('Cron is already running.');
    
    flock($fp, LOCK_UN);
    fclose($fp);
});

it('downloads avatar locally for activitypub create', function () use ($funcTempDir) {
    $db = Database::getDb();
    
    $activity = [
        'type' => 'Create',
        'actor' => 'https://remote.example.com/user',
        'object' => [
            'type' => 'Note',
            'id' => 'https://remote.example.com/post/1',
            'content' => 'Hello ActivityPub',
            'published' => '2026-06-30T10:00:00Z',
            'url' => 'https://remote.example.com/post/1'
        ]
    ];
    
    $payload = [
        'headers' => ['signature' => 'keyId="https://remote.example.com/user#main-key"'],
        'body' => json_encode($activity),
        'method' => 'POST',
        'path' => '/inbox'
    ];
    
    $db->exec("INSERT INTO inbox_queue (type, payload_json, created_at) VALUES ('activitypub', '" . json_encode($payload) . "', " . time() . ")");
    
    $mockActorData = [
        'name' => 'Remote User',
        'icon' => ['url' => 'https://remote.example.com/avatar.jpg'],
        'publicKey' => [
            'id' => 'https://remote.example.com/user#main-key',
            'publicKeyPem' => 'dummy-pem'
        ]
    ];

    $fetcher = function (string $url) {
        if (str_contains($url, 'avatar')) {
            return 'dummy image data';
        }
        return false;
    };
    $jsonFetcher = fn(string $url) => $mockActorData;

    $processor = new InboxProcessor($this->site, $db, $fetcher, $jsonFetcher);
    $processor->process();
    
    // Check if avatar was downloaded
    $avatarsDir = $funcTempDir . '/avatars/remote.example.com';
    expect(is_dir($avatarsDir))->toBeTrue();
    $files = scandir($avatarsDir);
    expect(count($files))->toBeGreaterThan(2); // . and .. and the image
    
    // Check microsub inbox file
    $inboxDir = $funcTempDir . '/microsub/inbox/inbox';
    $inboxFiles = glob($inboxDir . '/*.md');
    expect(count($inboxFiles))->toBe(1);
    
    $content = file_get_contents($inboxFiles[0]);
    expect($content)->toContain('author_name: Remote User');
    expect($content)->toContain('/data/avatars/remote.example.com/');
});

it('processes build_site queue correctly', function () use ($funcTempDir) {
    $db = Database::getDb();
    
    // Clear the html dir so we can verify if it creates a file
    $htmlDir = $funcTempDir . '/public_html';
    if (!is_dir($htmlDir)) {
        mkdir($htmlDir, 0777, true);
    }
    
    // Create a dummy content file to trigger an index build
    $contentDir = $funcTempDir . '/content/article/2026/06';
    if (!is_dir($contentDir)) {
        mkdir($contentDir, 0777, true);
    }
    file_put_contents($contentDir . '/test-build-bg.md', "---\ntitle: Test\n---\nHello");
    
    $db->exec("INSERT INTO inbox_queue (type, payload_json, created_at) VALUES ('build_site', '{}', " . time() . ")");
    
    $processor = new InboxProcessor($this->site, $db);
    ob_start();
    $processor->process();
    $output = ob_get_clean();
    
    expect($output)->toContain('Rebuilding static site...');
    expect($output)->toContain('Site rebuild completed.');
});

it('unwraps Lemmy Announce activities in inbox', function () use ($funcTempDir) {
    $db = Database::getDb();
    
    // Clear queue from previous tests
    $db->exec("DELETE FROM inbox_queue");
    // Clear inbox files from previous tests
    Helper::recursiveRmdir($funcTempDir . '/microsub/inbox/inbox');
    mkdir($funcTempDir . '/microsub/inbox/inbox', 0777, true);

    $activity = [
        'type' => 'Announce',
        'actor' => 'https://lemmy.eco.br/c/linux',
        'object' => [
            'type' => 'Page',
            'id' => 'https://lemmy.eco.br/post/999',
            'content' => 'Lemmy post content',
            'published' => '2026-06-30T10:00:00Z',
            'url' => 'https://lemmy.eco.br/post/999',
            'attributedTo' => 'https://lemmy.eco.br/u/lumen'
        ]
    ];
    
    $payload = [
        'headers' => ['signature' => 'keyId="https://lemmy.eco.br/c/linux#main-key"'],
        'body' => json_encode($activity),
        'method' => 'POST',
        'path' => '/inbox'
    ];
    
    $db->exec("INSERT INTO inbox_queue (type, payload_json, created_at) VALUES ('activitypub', '" . json_encode($payload) . "', " . time() . ")");
    
    $mockActorData = [
        'name' => 'Lumen Lemmy',
        'icon' => ['url' => 'https://lemmy.eco.br/avatar.jpg'],
        'publicKey' => [
            'id' => 'https://lemmy.eco.br/c/linux#main-key',
            'publicKeyPem' => 'dummy-pem'
        ]
    ];

    $processor = new InboxProcessor($this->site, $db, null, fn($url) => $mockActorData);
    $processor->process();
    
    // Check microsub inbox file
    $inboxDir = $funcTempDir . '/microsub/inbox/inbox';
    $inboxFiles = glob($inboxDir . '/*.md');
    
    // We expect the post to be saved as from 'Lumen Lemmy' instead of the Group
    $content = file_get_contents($inboxFiles[0]);
    expect($content)->toContain('author_name: Lumen Lemmy');
    expect($content)->toContain('Lemmy post content');
});

it('extracts BookWyrm properties from ActivityPub Create', function () use ($funcTempDir) {
    $db = Database::getDb();
    
    $activity = [
        'type' => 'Create',
        'actor' => 'https://bookwyrm.social/user/reader',
        'object' => [
            'type' => 'Article',
            'id' => 'https://bookwyrm.social/post/111',
            'content' => 'Great read',
            'published' => '2026-06-30T10:00:00Z',
            'url' => 'https://bookwyrm.social/post/111',
            'inReplyToBook' => 'https://bookwyrm.social/book/555',
            'rating' => 5,
            'readingStatus' => 'finished'
        ]
    ];
    
    $payload = [
        'headers' => ['signature' => 'keyId="https://bookwyrm.social/user/reader#main-key"'],
        'body' => json_encode($activity),
        'method' => 'POST',
        'path' => '/inbox'
    ];
    
    // Clear queue from previous tests
    $db->exec("DELETE FROM inbox_queue");
    // Clear inbox files from previous tests
    Helper::recursiveRmdir($funcTempDir . '/microsub/inbox/inbox');
    mkdir($funcTempDir . '/microsub/inbox/inbox', 0777, true);

    $db->exec("INSERT INTO inbox_queue (type, payload_json, created_at) VALUES ('activitypub', '" . json_encode($payload) . "', " . time() . ")");
    
    $mockActorData = [
        'name' => 'Reader',
        'icon' => ['url' => 'https://bookwyrm.social/avatar.jpg'],
        'publicKey' => [
            'id' => 'https://bookwyrm.social/user/reader#main-key',
            'publicKeyPem' => 'dummy-pem'
        ]
    ];

    $processor = new InboxProcessor($this->site, $db, null, fn($url) => $mockActorData);
    $processor->process();
    
    $inboxDir = $funcTempDir . '/microsub/inbox/inbox';
    $inboxFiles = glob($inboxDir . '/*.md');
    
    $content = file_get_contents($inboxFiles[0]);
    expect($content)->toContain('read_of: https://bookwyrm.social/book/555');
    expect($content)->toContain('rating: 5');
});
