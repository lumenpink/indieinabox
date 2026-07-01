<?php

declare(strict_types=1);

use Indieinabox\Site;

/**
 * @property Site $site
 * @property TestMicropubRouter $router
 */

// Subclass the handler to avoid calling headers and exit
class MockMicropubHandler extends \Indieinabox\MicropubHandler
{
    public array $lastResponse = [];
    public ?string $mockJsonInput = null;

    protected function getRawInput(): string
    {
        return $this->mockJsonInput ?? parent::getRawInput();
    }

    protected function sendResponse(int $code, string $error, string $description): void
    {
        $this->lastResponse = [
            'status' => $code,
            'body' => [
                'error' => $error,
                'error_description' => $description
            ],
            'headers' => []
        ];
    }
    protected function sendSuccessResponse(int $code, array $headers = [], $body = null): void
    {
        $this->lastResponse = [
            'status' => $code,
            'headers' => $headers,
            'body' => $body
        ];
    }

    protected function moveUploadedFile(string $tmpName, string $destPath): bool
    {
        return copy($tmpName, $destPath);
    }
}

class TestMicropubRouter extends \Indieinabox\WebRouter
{
    public MockMicropubHandler $micropubMock;

    public function __construct(Site $site)
    {
        parent::__construct($site);
        $this->micropubMock = new MockMicropubHandler($site);
    }

    protected function createMicropubHandler(): \Indieinabox\MicropubHandler
    {
        return $this->micropubMock;
    }
}

$funcTempDir = __DIR__ . '/tmp_functional_micropub';

beforeEach(function () use ($funcTempDir) {
    /** @var \PHPUnit\Framework\TestCase|mixed $this */
    if (!is_dir($funcTempDir)) {
        mkdir($funcTempDir, 0777, true);
    }
    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_FILES = [];
    
    // Set up test database
    $ref = new \ReflectionClass(\Indieinabox\Database::class);
    $prop = $ref->getProperty('db');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
    
    $testDbPath = $funcTempDir . '/test.sqlite';
    \Indieinabox\Database::$dataDir = $funcTempDir;
    \Indieinabox\Database::connect($testDbPath);
    $db = \Indieinabox\Database::getDb();
    $db->exec(file_get_contents(dirname(__DIR__, 2) . '/database.sql'));

    // Insert a valid token
    $db->exec("INSERT INTO indieauth_tokens (token_hash, client_id, scope, me, created_at) VALUES ('" . hash('sha256', 'valid-token-123') . "', 'https://client.example.com', 'create update delete media', 'https://example.com/', " . time() . ")");
    // Insert token without media scope
    $db->exec("INSERT INTO indieauth_tokens (token_hash, client_id, scope, me, created_at) VALUES ('" . hash('sha256', 'no-media-token') . "', 'https://client.example.com', 'update', 'https://example.com/', " . time() . ")");
    
    // Set Site configuration
    $paths = new \Indieinabox\Site\Paths($funcTempDir, $funcTempDir . '/public_html', $funcTempDir . '/public_gemini', $funcTempDir . '/public_gopher', $funcTempDir . '/public_media', $funcTempDir . '/content', $funcTempDir . '/resources');
    $this->site = new Site(null, $paths);
    if (!is_dir($this->site->paths->contentDir)) {
        mkdir($this->site->paths->contentDir, 0777, true);
    }
    
    $this->router = new TestMicropubRouter($this->site);
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

it('rejects missing bearer token', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/micropub';
    
    $this->router->handleRequest();
    
    expect($this->router->micropubMock->lastResponse['status'])->toBe(401);
});



it('handles q=config query', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/micropub';
    $_GET['q'] = 'config';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token-123';
    
    $this->router->handleRequest();
    
    expect($this->router->micropubMock->lastResponse['status'])->toBe(200);
    expect($this->router->micropubMock->lastResponse['body'])->toBeArray();
    expect($this->router->micropubMock->lastResponse['body'])->toHaveKey('media-endpoint');
});

it('handles q=syndicate-to query', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/micropub';
    $_GET['q'] = 'syndicate-to';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token-123';
    
    $this->router->handleRequest();
    
    expect($this->router->micropubMock->lastResponse['status'])->toBe(200);
    expect($this->router->micropubMock->lastResponse['body'])->toBeArray();
    expect($this->router->micropubMock->lastResponse['body'])->toHaveKey('syndicate-to');
});

it('creates an article post via JSON content', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/micropub';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token-123';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    
    // Simulate php://input with JSON
    $jsonPayload = json_encode([
        'type' => ['h-entry'],
        'properties' => [
            'name' => ['My Test Article'],
            'content' => ['This is the body of the article.'],
            'category' => ['test', 'php']
        ]
    ]);
    
    // Mock the payload by assigning it to a property in handler
    $this->router->micropubMock->mockJsonInput = $jsonPayload;
    
    $this->router->handleRequest();
    
    expect($this->router->micropubMock->lastResponse['status'])->toBe(202);
    expect($this->router->micropubMock->lastResponse['headers'])->toHaveKey('Location');
    
    // Verify file was created
    $location = $this->router->micropubMock->lastResponse['headers']['Location'];
    $slug = str_replace('.html', '', basename($location));
    $filePath = $this->site->paths->contentDir . '/article/' . date('Y/m/') . $slug . '.md';
    expect(file_exists($filePath))->toBeTrue();
    
    $content = file_get_contents($filePath);
    expect($content)->toContain('title: "My Test Article"');
    expect($content)->toContain('This is the body of the article.');
    expect($content)->toContain('- test');

    // Verify it queued the site build
    $db = \Indieinabox\Database::getDb();
    $stmt = $db->query("SELECT * FROM inbox_queue WHERE type = 'build_site'");
    $queueItem = $stmt->fetch(\PDO::FETCH_ASSOC);
    expect($queueItem)->not->toBeFalse();
});

it('creates a note via form-encoded content', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/micropub';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token-123';
    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    
    $_POST = [
        'h' => 'entry',
        'content' => 'Just a quick note here!',
        'category' => 'status'
    ];
    
    $this->router->handleRequest();
    
    expect($this->router->micropubMock->lastResponse['status'])->toBe(202);
    
    $location = $this->router->micropubMock->lastResponse['headers']['Location'];
    $slug = str_replace('.html', '', basename($location));
    $filePath = $this->site->paths->contentDir . '/note/' . date('Y/m/') . $slug . '.md';
    expect(file_exists($filePath))->toBeTrue();
    
    $content = file_get_contents($filePath);
    expect($content)->toContain('Just a quick note here!');
});

it('handles media upload and collision correctly', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/micropub/media';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token-123';
    
    $mediaDir = $this->site->paths->contentDir . '/media';
    if (!is_dir($mediaDir)) {
        mkdir($mediaDir, 0777, true);
    }
    
    // Create a dummy file
    $tmpFile = $this->site->paths->contentDir . '/tmp_upload.jpg';
    file_put_contents($tmpFile, 'dummy data');
    
    $_FILES = [
        'file' => [
            'name' => 'test-image.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 10
        ]
    ];
    
    $this->router->handleRequest();
    
    expect($this->router->micropubMock->lastResponse['status'])->toBe(201);
    expect($this->router->micropubMock->lastResponse['headers'])->toHaveKey('Location');
    
    // Test collision
    file_put_contents($tmpFile, 'dummy data');
    $this->router->handleRequest();
    
    expect($this->router->micropubMock->lastResponse['status'])->toBe(201);
    $location = $this->router->micropubMock->lastResponse['headers']['Location'];
    expect($location)->toContain('.jpg');
});

it('rejects media upload if token lacks media scope', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/micropub/media';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer no-media-token';
    
    $this->router->handleRequest();
    
    expect($this->router->micropubMock->lastResponse['status'])->toBe(403);
});
