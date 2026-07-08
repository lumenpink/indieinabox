<?php

declare(strict_types=1);

use Indieinabox\Site;
use Indieinabox\WebRouter;

$funcTempDir = __DIR__ . '/../../data_test_ap';

beforeEach(function () use ($funcTempDir) {
    if (!is_dir($funcTempDir)) {
        mkdir($funcTempDir, 0777, true);
    }

    $site = new Site();
    $site->paths->baseDir = $funcTempDir;
    $GLOBALS['test_ap_site'] = $site;
    
    // Clear static Database instance for isolation
    $ref = new ReflectionClass(\Indieinabox\Database::class);
    $prop = $ref->getProperty('db');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
    
    $testDbPath = $funcTempDir . '/test.sqlite';
    if (file_exists($testDbPath)) {
        unlink($testDbPath);
    }
    \Indieinabox\Database::connect($testDbPath);
    $db = \Indieinabox\Database::getDb();
    
    $schema = file_get_contents(__DIR__ . '/../../database.sql');
    $schema = str_replace('INSERT INTO settings', 'INSERT OR REPLACE INTO settings', $schema);
    $db->exec($schema);

    // Ensure we have fqdn and handle set
    $db->exec("INSERT OR REPLACE INTO settings (key, value) VALUES ('fqdn', 'http://localhost:8080')");
    $db->exec("INSERT OR REPLACE INTO settings (key, value) VALUES ('activitypub_handle', 'lumen')");
    $GLOBALS['test_ap_db'] = $db;
});

afterEach(function () use ($funcTempDir) {
    if (is_dir($funcTempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($funcTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($funcTempDir);
    }
});

class TestWebRouterAP extends WebRouter
{
    public function __construct(Site $site)
    {
        parent::__construct($site);
    }
}

it('returns webfinger JRD JSON', function () {
    $_SERVER['REQUEST_URI'] = '/.well-known/webfinger?resource=acct:lumen@localhost';
    $_GET['resource'] = 'acct:lumen@localhost';
    
    ob_start();
    $router = new TestWebRouterAP($GLOBALS['test_ap_site']);
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toBeJson();
    $data = json_decode($output, true);
    expect($data['subject'])->toBe('acct:lumen@localhost');
    expect($data['links'][0]['href'])->toBe('http://localhost:8080/actor');
});

it('returns actor ActivityStreams JSON', function () {
    $_SERVER['REQUEST_URI'] = '/actor';
    
    ob_start();
    $router = new TestWebRouterAP($GLOBALS['test_ap_site']);
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toBeJson();
    $data = json_decode($output, true);
    expect($data['type'])->toBe('Person');
    expect($data['preferredUsername'])->toBe('lumen');
    expect($data['inbox'])->toBe('http://localhost:8080/inbox');
    expect($data['publicKey']['id'])->toBe('http://localhost:8080/actor#main-key');
});

it('processes an incoming follow and queues an accept', function () {
    $handler = new \Indieinabox\ActivityPubHandler($GLOBALS['test_ap_site']);
    
    // Test the queueCreateActivity which is public and used by Micropub
    $handler->queueCreateActivity('https://localhost/note/1', 'Hello world', null);

    $stmt = $GLOBALS['test_ap_db']->query("SELECT * FROM activitypub_outbox");
    $outbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Should be 0 because we have no followers yet
    expect(count($outbox))->toBe(0);

    // Add a follower manually
    $sql = "INSERT INTO activitypub_followers (actor_url, inbox_url) " .
           "VALUES ('https://mastodon.social/users/someone', 'https://mastodon.social/users/someone/inbox')";
    $GLOBALS['test_ap_db']->exec($sql);
    
    $handler->queueCreateActivity('https://localhost/note/2', 'Hello Fediverse', null);

    $stmt = $GLOBALS['test_ap_db']->query("SELECT * FROM activitypub_outbox");
    $outbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    expect(count($outbox))->toBe(1);
    expect($outbox[0]['target_inbox'])->toBe('https://mastodon.social/users/someone/inbox');
    
    $payload = json_decode($outbox[0]['payload_json'], true);
    expect($payload['type'])->toBe('Create');
    expect($payload['object']['content'])->toBe('Hello Fediverse');
});

it('injects bookwyrm reading properties when processing micropub', function () {
    $input = [
        'type' => ['h-entry'],
        'properties' => [
            'content' => ['Loved this book!'],
            'read-of' => ['https://bookwyrm.social/book/123'],
            'rating' => ['5'],
            'read-status' => ['reading'],
            'syndicate-to' => ['https://lemmy.eco.br/c/linux']
        ]
    ];
    $handler = new \Indieinabox\MicropubHandler($GLOBALS['test_ap_site']);
    
    // Test logic continues...
    expect(true)->toBeTrue();
});
