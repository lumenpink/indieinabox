<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\SiteBuilder;
use Indieinabox\Page;

beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->tempDir = sys_get_temp_dir() . '/indieinabox_interactions_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content/post', 0777, true);
    
    file_put_contents($this->tempDir . '/content/post/test-interactions.md', "---\ntitle: Test Interactions\nslug: test-interactions\n---\nContent here.");
    
    $this->paths = new Paths(
        $this->tempDir,
        'public_html',
        'public_gemini',
        'public_gopher',
        'public_media',
        'content',
        'resources'
    );
    $this->site = new Site(null, $this->paths);
    
    // Set absolute paths for view loading (since views are loaded from actual app dir)
    $this->appViewsDir = __DIR__ . '/../../resources/views';
    
    // Set up test database
    $ref = new \ReflectionClass(\Indieinabox\Database::class);
    $prop = $ref->getProperty('db');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
    
    $testDbPath = $this->tempDir . '/test.sqlite';
    \Indieinabox\Database::$dataDir = $this->tempDir . '/data';
    \Indieinabox\Database::connect($testDbPath);
    $db = \Indieinabox\Database::getDb();
    $db->exec(file_get_contents(dirname(__DIR__, 2) . '/database.sql'));
});

afterEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    \Indieinabox\Helper::recursiveRmdir($this->tempDir);
});

it('displays distinct interaction links on index and post pages', function () {
    /** @var \Tests\TestCase|mixed $this */
    
    // Mock interactions in files
    $hash = md5('post/test-interactions/');
    $notificationsDir = $this->tempDir . '/data/microsub/inbox/notifications';
    if (!is_dir($notificationsDir)) {
        mkdir($notificationsDir, 0777, true);
    }
    
    // Add an approved like
    $likeYaml = "---\ninteraction_type: like\nurl: https://example.com/like\nauthor_name: Test Liker\nstatus: approved\n---\n";
    file_put_contents($notificationsDir . '/' . $hash . '_like1.md', $likeYaml);
    
    // Add an approved reply
    $replyYaml = "---\ninteraction_type: reply\nurl: https://example.com/reply\nauthor_name: Test Replier\nstatus: approved\n---\nThis is a test reply";
    file_put_contents($notificationsDir . '/' . $hash . '_reply1.md', $replyYaml);
    
    // Add a pending reply
    $pendingYaml = "---\ninteraction_type: reply\nurl: https://example.com/pending\nauthor_name: Test Pending\nstatus: pending\n---\nThis is a pending reply that should not be visible";
    file_put_contents($notificationsDir . '/' . $hash . '_pending1.md', $pendingYaml);

    // Build the page
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    
    $pages = iterator_to_array($builder->getPages(), false);
    $page = $pages[0];
    
    Assert::assertEquals('post/test-interactions/', $page->slug);
    
    $site = clone clone $this->site; // Mock site context
    
    // Test the summary output (index page)
    global $site;
    $site = $this->site;
    ob_start();
    include $this->appViewsDir . '/includes/summary.php';
    $summaryHtml = ob_get_clean();
    
    // Check distinct links
    Assert::assertStringContainsString('interactions#likes', $summaryHtml);
    Assert::assertStringContainsString('#interactions', $summaryHtml);

    // Test the post page output
    ob_start();
    include $this->appViewsDir . '/page.php';
    $pageHtml = ob_get_clean();
    
    // Check top metadata
    Assert::assertStringContainsString('interactions#likes', $pageHtml);
    
    // Check interactions block at the bottom
    Assert::assertStringContainsString('interactions#likes', $pageHtml);
    
    // Check reply wrapping anchor
    $replyHash = md5('https://example.com/reply');
    Assert::assertStringContainsString('reply/' . $replyHash . '/', $pageHtml);
    Assert::assertStringContainsString('This is a test reply', $pageHtml);
    
    // Check pending reply is NOT visible
    Assert::assertStringNotContainsString('This is a pending reply', $pageHtml);
    Assert::assertStringNotContainsString('Test Pending', $pageHtml);
});
