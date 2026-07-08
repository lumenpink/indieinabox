<?php
declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\SiteBuilder;
use Indieinabox\WebRouter;

beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->tempDir = sys_get_temp_dir() . '/indieinabox_ap_page_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content/post', 0777, true);

    $this->paths = new Paths($this->tempDir, 'public_html', 'public_gemini', 'public_gopher', 'public_media', 'content', 'resources');
    $this->site = new Site(null, $this->paths);
    $this->site->metadata->fqdn = 'https://example.com';
    
    global $site;
    $site = clone $this->site;
});

afterEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    \Indieinabox\Helper::recursiveRmdir($this->tempDir);
});

it('generates index.json ActivityPub representation for posts', function () {
    /** @var \Tests\TestCase|mixed $this */
    $yaml = "---\nkind: article\ntitle: AP Test\nsyndicate_to: https://mastodon.social/@user\n---\nHello AP";
    file_put_contents($this->tempDir . '/content/post/test-ap.md', $yaml);

    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    $builder->generateHTMLFiles();

    $jsonFile = $this->tempDir . '/public_html/post/test-ap/index.json';
    expect(file_exists($jsonFile))->toBeTrue();

    $content = file_get_contents($jsonFile);
    $data = json_decode($content, true);

    expect($data['type'])->toBe('Article');
    expect($data['name'])->toBe('AP Test');
    expect($data['content'])->toContain('Hello AP');
    expect($data['attributedTo'])->toBe('https://example.com/actor');
});

it('serves index.json via WebRouter when Accept header requests ActivityPub', function () {
    /** @var \Tests\TestCase|mixed $this */
    $yaml = "---\nkind: article\ntitle: Router Test\n---\nRouting works";
    file_put_contents($this->tempDir . '/content/post/router-test.md', $yaml);

    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    $builder->generateHTMLFiles();

    $_SERVER['REQUEST_URI'] = '/post/router-test';
    $_SERVER['HTTP_ACCEPT'] = 'application/activity+json, application/json';

    ob_start();
    $router = new WebRouter($this->site);
    $router->handleRequest();
    $output = ob_get_clean();

    $data = json_decode($output, true);
    expect($data)->toBeArray();
    expect($data['type'])->toBe('Article');
    expect($data['name'])->toBe('Router Test');
    expect($data['content'])->toContain('Routing works');
});
