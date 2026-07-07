<?php

declare(strict_types=1);

use Indieinabox\Site;

/**
 * @property Site $site
 * @property MockBackgroundWorkerBgTest $worker
 */
class MockBackgroundWorkerBgTest extends \Indieinabox\BackgroundWorker
{
    public array $calledArchiveOrg = [];
    public array $calledMicrolink = [];
    public array $calledFetchUrl = [];
    
    public ?string $mockFinalUrl = null;
    public ?string $mockPdfPath = null;
    public ?array $mockActorData = null;
    
    protected function fetchJsonUrl(string $url): ?array
    {
        if (strpos($url, 'remote.example.com') !== false && $this->mockActorData !== null) {
            return $this->mockActorData;
        }
        return parent::fetchJsonUrl($url);
    }
    
    protected function resolveFinalUrl(string $url): string
    {
        return $this->mockFinalUrl ?? $url;
    }

    protected function sendToArchiveOrg(string $url): void
    {
        $this->calledArchiveOrg[] = $url;
    }

    protected function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string
    {
        $this->calledMicrolink[] = $url;
        if ($this->mockPdfPath) {
            $filename = md5($normUrl . time()) . '.pdf';
            $filepath = $pdfDir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($filepath, 'dummy pdf data');
            return $this->mockPdfPath;
        }
        return null;
    }
    
    protected function fetchUrl(string $url): string|bool
    {
        $this->calledFetchUrl[] = $url;
        if (strpos($url, 'avatar') !== false) {
            return 'dummy image data';
        }
        return false;
    }
}

$funcTempDir = __DIR__ . '/tmp_functional_bgworker';

beforeEach(function () use ($funcTempDir) {
    /** @var \PHPUnit\Framework\TestCase|mixed $this */
    if (!is_dir($funcTempDir)) {
        mkdir($funcTempDir, 0777, true);
    }
    
    // Set up test database
    $ref = new \ReflectionClass(\Indieinabox\Database::class);
    $prop = $ref->getProperty('db');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
    
    $testDbPath = $funcTempDir . '/test.sqlite';
    if (file_exists($testDbPath)) {
        unlink($testDbPath);
    }
    
    \Indieinabox\Database::$dataDir = $funcTempDir;
    \Indieinabox\Database::connect($testDbPath);
    $db = \Indieinabox\Database::getDb();
    
    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/../../database.sql');
    $db->exec($schema);
    
    $paths = new \Indieinabox\Site\Paths($funcTempDir, $funcTempDir . '/public_html', $funcTempDir . '/public_gemini', $funcTempDir . '/public_gopher', $funcTempDir . '/public_media', $funcTempDir . '/content', $funcTempDir . '/resources');
    $this->site = new Site(null, $paths);
    $GLOBALS['site'] = $this->site;
    
    $this->worker = new MockBackgroundWorkerBgTest($this->site);
});

afterEach(function () use ($funcTempDir) {
    $ref = new \ReflectionClass(\Indieinabox\Database::class);
    $prop = $ref->getProperty('db');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
});

it('processes archive queue and saves to db', function () {
    $db = \Indieinabox\Database::getDb();
    
    $url = 'https://example.com/post/1';
    $db->exec("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES ('$url', " . time() . ", 0, 'pending')");
    
    $this->worker->mockPdfPath = '/data/archives/dummy.pdf';
    
    $this->worker->processArchiveQueue();
    
    expect($this->worker->calledArchiveOrg)->toContain($url);
    expect($this->worker->calledMicrolink)->toContain($url);
    
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
    $db = \Indieinabox\Database::getDb();
    
    $url = 'https://example.com/post/2';
    $db->exec("INSERT INTO archived_links (url, timestamp, local_pdf_path, archive_org_url) VALUES ('$url', " . time() . ", null, null)");
    
    // Regular request (should skip)
    $db->exec("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES ('$url', " . time() . ", 0, 'pending')");
    
    $this->worker->processArchiveQueue();
    
    expect($this->worker->calledArchiveOrg)->toBeEmpty();
    
    // Force request (should process)
    $db->exec("INSERT INTO archive_queue (url, requested_at, force_archive, status) VALUES ('$url', " . time() . ", 1, 'pending')");
    
    $this->worker->processArchiveQueue();
    
    expect($this->worker->calledArchiveOrg)->toContain($url);
});

it('cannot run concurrently due to flock', function () use ($funcTempDir) {
    // Open a lock manually
    $lockFile = $funcTempDir . '/cron.lock';
    $fp = fopen($lockFile, 'w+');
    flock($fp, LOCK_EX | LOCK_NB);
    
    ob_start();
    $this->worker->runAll();
    $output = ob_get_clean();
    
    expect($output)->toContain('Cron is already running.');
    
    flock($fp, LOCK_UN);
    fclose($fp);
});

it('downloads avatar locally for activitypub create', function () use ($funcTempDir) {
    $db = \Indieinabox\Database::getDb();
    
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
    
    $this->worker->mockActorData = [
        'name' => 'Remote User',
        'icon' => ['url' => 'https://remote.example.com/avatar.jpg'],
        'publicKey' => [
            'id' => 'https://remote.example.com/user#main-key',
            'publicKeyPem' => 'dummy-pem'
        ]
    ];
    
    $this->worker->processInboxQueue();
    
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
    $db = \Indieinabox\Database::getDb();
    
    // Clear the html dir so we can verify if it creates a file
    $htmlDir = $funcTempDir . '/public_html';
    if (!is_dir($htmlDir)) mkdir($htmlDir, 0777, true);
    
    // Create a dummy content file to trigger an index build
    $contentDir = $funcTempDir . '/content/article/2026/06';
    if (!is_dir($contentDir)) mkdir($contentDir, 0777, true);
    file_put_contents($contentDir . '/test-build-bg.md', "---\ntitle: Test\n---\nHello");
    
    $db->exec("INSERT INTO inbox_queue (type, payload_json, created_at) VALUES ('build_site', '{}', " . time() . ")");
    
    // We mock output capture because SiteBuilder produces output
    ob_start();
    $this->worker->processInboxQueue();
    $output = ob_get_clean();
    
    expect($output)->toContain('Rebuilding static site...');
    expect($output)->toContain('Site rebuild completed.');
    expect($output)->toContain('Rebuilding static site...');
    expect($output)->toContain('Site rebuild completed.');
});
