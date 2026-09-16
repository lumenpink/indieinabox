<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Indieinabox\Core\Database;
use Indieinabox\Http\Controllers\WebmentionController;
use Indieinabox\Services\WebmentionService;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Support\FileUtils;
use PDO;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_wm_ctrl_test_' . uniqid();
    mkdir($this->tempDir . '/public_html', 0777, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir . '/data';
    Database::connect(':memory:');
    $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    Database::getDb()->exec($sql);

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);
    $this->site->metadata->fqdn = 'https://myblog.com/';

    file_put_contents($this->tempDir . '/public_html/about.html', '<h1>About</h1>');
    file_put_contents($this->tempDir . '/public_html/index.html', '<h1>Home</h1>');

    $this->service = new WebmentionService(Database::getDb());
    $this->controller = new WebmentionController($this->site, $this->service);

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    Database::disconnect();
    FileUtils::recursiveRmdir($this->tempDir);
});

it('renders help page on GET request', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';

    ob_start();
    $this->controller->handle();
    $output = ob_get_clean();

    expect($output)->toContain('Webmention Endpoint')
        ->toContain('https://myblog.com/');
});

it('rejects POST request with missing source or target parameters', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['source' => 'https://external.com/post'];

    ob_start();
    $this->controller->handle();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(400)
        ->and($output)->toContain('Missing source or target parameters');
});

it('rejects POST request with invalid target domain', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'source' => 'https://external.com/post',
        'target' => 'https://otherdomain.com/post',
    ];

    ob_start();
    $this->controller->handle();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(400)
        ->and($output)->toContain('Target URL does not belong to this site');
});

it('rejects POST request when source and target are identical', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'source' => 'https://myblog.com/about.html',
        'target' => 'https://myblog.com/about.html',
    ];

    ob_start();
    $this->controller->handle();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(400)
        ->and($output)->toContain('Source and target URLs cannot be identical');
});

it('rejects POST request when target page does not exist on site', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'source' => 'https://external.com/post',
        'target' => 'https://myblog.com/non-existent-page',
    ];

    ob_start();
    $this->controller->handle();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(400)
        ->and($output)->toContain('Target page not found on this site');
});

it('accepts and queues valid webmention returning 202 Accepted', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'source' => 'https://external.com/post',
        'target' => 'https://myblog.com/about.html',
    ];

    ob_start();
    $this->controller->handle();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(202)
        ->and($output)->toContain('Webmention accepted and queued for processing');

    $stmt = Database::getDb()->query("SELECT * FROM inbox_queue WHERE type = 'webmention'");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    expect(count($items))->toBe(1);

    $payload = json_decode((string) $items[0]['payload_json'], true);
    expect($payload['source'])->toBe('https://external.com/post')
        ->and($payload['target'])->toBe('https://myblog.com/about.html');
});
