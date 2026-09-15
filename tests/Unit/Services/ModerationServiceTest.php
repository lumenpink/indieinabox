<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Services\ModerationService;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_mod_srv_test_' . uniqid();
    mkdir($this->tempDir);
    $this->notifDir = $this->tempDir . '/microsub/inbox/notifications';
    $this->spamDir = $this->tempDir . '/microsub/inbox/spam';
    mkdir($this->notifDir, 0777, true);
    mkdir($this->spamDir, 0777, true);

    $this->service = new ModerationService($this->tempDir);
});

afterEach(function () {
    \Indieinabox\Support\FileUtils::recursiveRmdir($this->tempDir);
});

test('ModerationService lists, approves, and deletes interactions', function () {
    // Create a pending notification
    $notifContent = "---\nauthor: Alice\nstatus: pending\n---\n\nNice post!";
    file_put_contents($this->notifDir . '/msg-1.md', $notifContent);

    // Create a spam notification
    $spamContent = "---\nauthor: Spammer\nstatus: spam\n---\n\nBuy crypto";
    file_put_contents($this->spamDir . '/spam-1.md', $spamContent);

    $pending = $this->service->listInteractions('pending');
    expect($pending)->toHaveCount(1);
    expect($pending[0]['id'])->toBe('msg-1');
    expect($pending[0]['body'])->toBe('Nice post!');

    $spam = $this->service->listInteractions('spam');
    expect($spam)->toHaveCount(1);
    expect($spam[0]['id'])->toBe('spam-1');

    // Approve pending notification
    $approved = $this->service->approveInteraction('msg-1', 'pending');
    expect($approved)->toBeTrue();

    $approvedList = $this->service->listInteractions('approved');
    expect($approvedList)->toHaveCount(1);
    expect($approvedList[0]['id'])->toBe('msg-1');
    expect($approvedList[0]['status'])->toBe('approved');

    // Delete spam
    $deleted = $this->service->deleteInteraction('spam-1', 'spam');
    expect($deleted)->toBeTrue();
    expect($this->service->listInteractions('spam'))->toBeEmpty();
});
