<?php

declare(strict_types=1);

use Indieinabox\Site;

// We need a mock WebRouter or MicrosubHandler
class TestMicrosubRouter extends \Indieinabox\WebRouter
{
    public static bool $mockTokenValid = true;

    protected function createMicrosubHandler(): \Indieinabox\MicrosubHandler
    {
        $handler = new class($this->site) extends \Indieinabox\MicrosubHandler {
            public function __construct(Site $site)
            {
                parent::__construct($site);

                // Override the internal IndieAuthHandler
                $ref = new \ReflectionClass(parent::class);
                $prop = $ref->getProperty('authHandler');
                $prop->setAccessible(true);

                $mockAuth = new class($site) extends \Indieinabox\IndieAuthHandler {
                    public function validateBearerToken(?string &$tokenOut = null): ?array
                    {
                        if (\TestMicrosubRouter::$mockTokenValid) {
                            return ['me' => 'https://mysite.com/', 'client_id' => 'test_client', 'scope' => 'read'];
                        }
                        return null;
                    }
                };
                $prop->setValue($this, $mockAuth);
            }
        };

        return $handler;
    }
}

$funcTempDir = __DIR__ . '/tmp_functional_microsub';

beforeEach(function () use ($funcTempDir) {
    if (!is_dir($funcTempDir)) {
        mkdir($funcTempDir, 0777, true);
    }

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_REQUEST = [];

    // Set up test database
    $ref = new \ReflectionClass(\Indieinabox\Database::class);
    $prop = $ref->getProperty('db');
    $prop->setAccessible(true);
    $prop->setValue(null, null);

    $testDbPath = $funcTempDir . '/test.sqlite';
    if (file_exists($testDbPath)) {
        unlink($testDbPath);
    }
    \Indieinabox\Database::$dataDir = $funcTempDir . '/data';
    \Indieinabox\Database::connect($testDbPath);
    $db = \Indieinabox\Database::getDb();

    $schema = file_get_contents(__DIR__ . '/../../database.sql');
    $db->exec($schema);
});

afterEach(function () use ($funcTempDir) {
    if (is_dir($funcTempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($funcTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($funcTempDir);
    }
});

it('rejects unauthenticated requests', function () use ($funcTempDir) {
    $site = new Site();
    TestMicrosubRouter::$mockTokenValid = false;
    $router = new TestMicrosubRouter($site);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/microsub';
    $_REQUEST['action'] = 'channels';

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json['error'])->toBe('unauthorized');
});

it('returns channels list', function () use ($funcTempDir) {
    $site = new Site();
    TestMicrosubRouter::$mockTokenValid = true;
    $router = new TestMicrosubRouter($site);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/microsub';
    $_REQUEST['action'] = 'channels';
    $_GET['action'] = 'channels';

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json['channels'])->toBeArray();
    expect($json['channels'][0]['uid'])->toBe('inbox');
});

it('allows following a url', function () use ($funcTempDir) {
    $site = new Site();
    TestMicrosubRouter::$mockTokenValid = true;
    $router = new TestMicrosubRouter($site);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/microsub';
    $_REQUEST['action'] = 'follow';
    $_POST['action'] = 'follow';
    $_POST['channel'] = 'inbox';
    $_POST['url'] = 'https://example.com/feed.xml';

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json['type'])->toBe('feed');
    expect($json['url'])->toBe('https://example.com/feed.xml');

    $db = \Indieinabox\Database::getDb();
    $stmt = $db->query("SELECT * FROM microsub_subscriptions WHERE channel_uid = 'inbox'");
    $subs = $stmt->fetchAll();
    expect(count($subs))->toBe(1);
    expect($subs[0]['url'])->toBe('https://example.com/feed.xml');
});

it('fetches timeline with pagination', function () use ($funcTempDir) {
    $site = new Site();
    TestMicrosubRouter::$mockTokenValid = true;
    $router = new TestMicrosubRouter($site);

    $dataDir = $funcTempDir . '/data';
    $inboxDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'inbox';
    if (!is_dir($inboxDir)) {
        @mkdir($inboxDir, 0755, true);
    }
    
    $yamlParser = new \Indieinabox\Yaml();
    
    $fm1 = $yamlParser->dump(['id' => 'id1', 'url' => 'http://1', 'published' => 1000, 'author_name' => 'Author', 'is_read' => 0]);
    file_put_contents($inboxDir . '/id1.md', "---\n$fm1---\n\npost 1");
    
    $fm2 = $yamlParser->dump(['id' => 'id2', 'url' => 'http://2', 'published' => 2000, 'author_name' => 'Author', 'is_read' => 0]);
    file_put_contents($inboxDir . '/id2.md', "---\n$fm2---\n\npost 2");
    
    $fm3 = $yamlParser->dump(['id' => 'id3', 'url' => 'http://3', 'published' => 3000, 'author_name' => 'Author', 'is_read' => 0]);
    file_put_contents($inboxDir . '/id3.md', "---\n$fm3---\n\npost 3");

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/microsub';
    $_REQUEST['action'] = 'timeline';
    $_GET['channel'] = 'inbox';

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect(count($json['items']))->toBe(3);
    // ordered by published DESC
    expect($json['items'][0]['_id'])->toBe('id3');
    expect($json['paging']['before'])->toBe(3000);
    expect($json['paging']['after'])->toBe(1000);

    // Test pagination (before = 2000 => items > 2000 => id3)
    $_GET['before'] = 2000;
    ob_start();
    $router->handleRequest();
    $output2 = ob_get_clean();
    $json2 = json_decode($output2, true);
    expect(count($json2['items']))->toBe(1);
    expect($json2['items'][0]['_id'])->toBe('id3');
});
