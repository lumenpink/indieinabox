<?php

declare(strict_types=1);

use Indieinabox\Site;

/**
 * @property Site $site
 * @property MockMicropubTypesHandler $micropubMock
 */

// Subclass the handler to avoid calling headers and exit
class MockMicropubTypesHandler extends \Indieinabox\MicropubHandler
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
}

$funcTypesTempDir = __DIR__ . '/tmp_functional_micropub_types';

beforeEach(function () use ($funcTypesTempDir) {
    if (!is_dir($funcTypesTempDir)) {
        mkdir($funcTypesTempDir, 0777, true);
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
    
    $testDbPath = $funcTypesTempDir . '/test.sqlite';
    \Indieinabox\Database::$dataDir = $funcTypesTempDir;
    \Indieinabox\Database::connect($testDbPath);
    $db = \Indieinabox\Database::getDb();
    $db->exec(file_get_contents(dirname(__DIR__, 2) . '/database.sql'));

    // Insert a valid token
    $db->exec("INSERT INTO indieauth_tokens (token_hash, client_id, scope, me, created_at) VALUES ('" . hash('sha256', 'valid-token-123') . "', 'https://client.example.com', 'create update delete media', 'https://example.com/', " . time() . ")");
    
    // Set Site configuration
    $paths = new \Indieinabox\Site\Paths($funcTypesTempDir, $funcTypesTempDir . '/public_html', $funcTypesTempDir . '/public_gemini', $funcTypesTempDir . '/public_gopher', $funcTypesTempDir . '/public_media', $funcTypesTempDir . '/content', $funcTypesTempDir . '/resources');
    $this->site = new Site(null, $paths);
    if (!is_dir($this->site->paths->contentDir)) {
        mkdir($this->site->paths->contentDir, 0777, true);
    }
    
    $this->micropubMock = new MockMicropubTypesHandler($this->site);
});

afterEach(function () use ($funcTypesTempDir) {
    if (is_dir($funcTypesTempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($funcTypesTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($funcTypesTempDir);
    }
});

function helperFindLastCreatedPost(string $contentDir): ?string {
    $files = [];
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($contentDir));
    foreach ($iter as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $files[$file->getPathname()] = $file->getMTime();
        }
    }
    arsort($files);
    return key($files);
}

it('discovers reply type correctly', function () {
    $_SERVER['REQUEST_URI'] = '/micropub';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token-123';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    
    $payload = [
        'type' => ['h-entry'],
        'properties' => [
            'content' => ['This is a reply.'],
            'in-reply-to' => ['https://example.com/post/123']
        ]
    ];
    $this->micropubMock->mockJsonInput = json_encode($payload);
    $this->micropubMock->handle();
    
    $lastPostPath = helperFindLastCreatedPost($this->site->paths->contentDir);
    expect($lastPostPath)->not->toBeNull();
    
    // Check if it's saved in the reply directory
    expect(strpos($lastPostPath, '/content/reply/'))->not->toBeFalse();
    
    $content = file_get_contents($lastPostPath);
    expect($content)->toContain('in_reply_to: "https://example.com/post/123"');
});

it('discovers like type correctly', function () {
    $_SERVER['REQUEST_URI'] = '/micropub';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token-123';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    
    $payload = [
        'type' => ['h-entry'],
        'properties' => [
            'like-of' => ['https://example.com/post/123']
        ]
    ];
    $this->micropubMock->mockJsonInput = json_encode($payload);
    $this->micropubMock->handle();
    
    $lastPostPath = helperFindLastCreatedPost($this->site->paths->contentDir);
    expect($lastPostPath)->not->toBeNull();
    expect(strpos($lastPostPath, '/content/like/'))->not->toBeFalse();
    
    $content = file_get_contents($lastPostPath);
    expect($content)->toContain('like_of: "https://example.com/post/123"');
});

it('discovers rsvp type correctly', function () {
    $_SERVER['REQUEST_URI'] = '/micropub';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token-123';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    
    $payload = [
        'type' => ['h-entry'],
        'properties' => [
            'in-reply-to' => ['https://example.com/event/123'],
            'rsvp' => ['yes']
        ]
    ];
    $this->micropubMock->mockJsonInput = json_encode($payload);
    $this->micropubMock->handle();
    
    $lastPostPath = helperFindLastCreatedPost($this->site->paths->contentDir);
    expect($lastPostPath)->not->toBeNull();
    
    // According to W3C, rsvp > in-reply-to
    expect(strpos($lastPostPath, '/content/rsvp/'))->not->toBeFalse();
    
    $content = file_get_contents($lastPostPath);
    expect($content)->toContain('rsvp: "yes"');
    expect($content)->toContain('in_reply_to: "https://example.com/event/123"');
});

it('discovers bookmark type correctly', function () {
    $_SERVER['REQUEST_URI'] = '/micropub';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token-123';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    
    $payload = [
        'type' => ['h-entry'],
        'properties' => [
            'bookmark-of' => ['https://example.com/article/123']
        ]
    ];
    $this->micropubMock->mockJsonInput = json_encode($payload);
    $this->micropubMock->handle();
    
    $lastPostPath = helperFindLastCreatedPost($this->site->paths->contentDir);
    expect($lastPostPath)->not->toBeNull();
    expect(strpos($lastPostPath, '/content/bookmark/'))->not->toBeFalse();
});
