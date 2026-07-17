<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\ModerationHandler;

beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->tempDir = sys_get_temp_dir() . '/indieinabox_moderation_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/data/microsub/inbox/notifications', 0777, true);
    
    $this->paths = new Paths(
        realpath(__DIR__ . '/../../'),
        'public_html',
        'public_gemini',
        'public_gopher',
        'public_media',
        'content',
        'resources'
    );
    $this->site = new Site(null, $this->paths);
    \Indieinabox\Database::$dataDir = $this->tempDir . '/data';
    
    $_SESSION = [];
    $_POST = [];
    $_SERVER = [];
});

afterEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    \Indieinabox\Helper::recursiveRmdir($this->tempDir);
});

it('redirects to config if not authenticated', function () {
    /** @var \Tests\TestCase|mixed $this */
    
    $handler = new ModerationHandler($this->site);
    
    $_SESSION['admin_authenticated'] = false;
    ob_start();
    $handler->handle();
    $output = ob_get_clean();
    
    // We expect it to redirect and return without rendering HTML
    Assert::assertStringNotContainsString('Moderation', $output);
});

it('lists pending interactions', function () {
    /** @var \Tests\TestCase|mixed $this */
    $_SESSION['admin_authenticated'] = true;
    
    $notificationsDir = $this->tempDir . '/data/microsub/inbox/notifications';
    
    // Pending
    $pendingYaml = "---\nid: test_pending_1\ninteraction_type: like\nurl: https://example.com/like\nauthor_name: Test Liker\nstatus: pending\n---\n";
    file_put_contents($notificationsDir . '/hash_pending1.md', $pendingYaml);
    
    // Approved
    $approvedYaml = "---\nid: test_approved_1\ninteraction_type: reply\nurl: https://example.com/reply\nauthor_name: Test Replier\nstatus: approved\n---\nBody";
    file_put_contents($notificationsDir . '/hash_reply1.md', $approvedYaml);

    $handler = new ModerationHandler($this->site);
    ob_start();
    $handler->handle();
    $output = ob_get_clean();
    
    Assert::assertStringContainsString('Test Liker', $output); // Pending should be listed
    Assert::assertStringNotContainsString('Test Replier', $output); // Approved should NOT be listed
});

it('approves a pending interaction', function () {
    /** @var \Tests\TestCase|mixed $this */
    $_SESSION['admin_authenticated'] = true;
    
    $notificationsDir = $this->tempDir . '/data/microsub/inbox/notifications';
    $pendingYaml = "---\nid: test_pending_1\ninteraction_type: like\nurl: https://example.com/like\nauthor_name: Test Liker\nstatus: pending\n---\n";
    $filePath = $notificationsDir . '/hash_pending1.md';
    file_put_contents($filePath, $pendingYaml);
    
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'action' => 'approve',
        'id' => 'hash_pending1'
    ];
    
    $handler = new ModerationHandler($this->site);
    ob_start();
    $handler->handle();
    ob_end_clean();
    
    // Verify file is now approved
    $content = file_get_contents($filePath);
    Assert::assertStringContainsString('status: approved', $content);
});

it('deletes a pending interaction', function () {
    /** @var \Tests\TestCase|mixed $this */
    $_SESSION['admin_authenticated'] = true;
    
    $notificationsDir = $this->tempDir . '/data/microsub/inbox/notifications';
    $pendingYaml = "---\nid: test_pending_1\ninteraction_type: like\nurl: https://example.com/like\nauthor_name: Test Liker\nstatus: pending\n---\n";
    $filePath = $notificationsDir . '/hash_pending1.md';
    file_put_contents($filePath, $pendingYaml);
    
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'action' => 'delete',
        'id' => 'hash_pending1'
    ];
    
    $handler = new ModerationHandler($this->site);
    ob_start();
    $handler->handle();
    ob_end_clean();
    
    // Verify file is deleted
    Assert::assertFileDoesNotExist($filePath);
});
