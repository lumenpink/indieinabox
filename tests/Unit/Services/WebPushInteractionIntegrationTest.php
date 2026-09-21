<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\DTO\InteractionDto;
use Indieinabox\Events\EventDispatcher;
use Indieinabox\Repositories\FileInteractionRepository;
use Indieinabox\Services\Contracts\WebPushServiceInterface;
use Indieinabox\Services\IngestInteractionService;
use Indieinabox\Services\ModerationService;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_webpush_int_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/microsub/inbox/notifications', 0777, true);
    mkdir($this->tempDir . '/microsub/inbox/spam', 0777, true);

    $this->repo = new FileInteractionRepository($this->tempDir);
    $this->dispatcher = new EventDispatcher();

    $this->broadcastCalls = [];
    $this->mockWebPush = new class($this->broadcastCalls) implements WebPushServiceInterface {
        /** @var array<int, array{title: string, body: string, url: ?string, tag: ?string}> */
        public array $broadcastCalls;

        public function __construct(array &$broadcastCalls)
        {
            $this->broadcastCalls = &$broadcastCalls;
        }

        public function getVapidPublicKey(): string { return 'test-key'; }
        public function getVapidKeys(): array { return ['publicKey' => 'test-pub', 'privateKey' => 'test-priv']; }
        public function createVapidJwt(string $audience, ?int $expiresIn = null): string { return 'test-jwt'; }
        public function subscribe(string $endpoint, string $p256dh, string $auth, ?string $userAgent = null): bool { return true; }
        public function unsubscribe(string $endpoint): bool { return true; }
        public function getSubscriptions(): array { return []; }
        public function sendNotification(array $subscription, array $payload): bool { return true; }
        public function broadcast(string $title, string $body, ?string $url = null, ?string $tag = null): int
        {
            $this->broadcastCalls[] = [
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'tag' => $tag,
            ];
            return 1;
        }
    };
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

test('IngestInteractionService broadcasts web push alert on notifications channel', function () {
    $service = new IngestInteractionService($this->repo, $this->dispatcher, $this->mockWebPush);

    $dto = new InteractionDto(
        'notif-1',
        'https://example.com/post',
        'https://remote.com/mention',
        'reply',
        'webmention',
        'Bob Builder',
        'https://remote.com/bob',
        null,
        'Awesome update!',
        new \DateTimeImmutable(),
        'pending'
    );

    $saved = $service->ingest($dto, 'notifications');
    expect($saved)->toBeTrue();
    expect($this->broadcastCalls)->toHaveCount(1);
    expect($this->broadcastCalls[0]['title'])->toBe('Reply from Bob Builder');
    expect($this->broadcastCalls[0]['body'])->toBe('Awesome update!');
    expect($this->broadcastCalls[0]['url'])->toBe('/microsub/reader?channel=notifications');
    expect($this->broadcastCalls[0]['tag'])->toBe('interaction-notif-1');
});

test('IngestInteractionService does not broadcast web push for non-notification channels', function () {
    $service = new IngestInteractionService($this->repo, $this->dispatcher, $this->mockWebPush);

    $dto = new InteractionDto(
        'spam-1',
        'https://example.com/post',
        'https://remote.com/spam',
        'reply',
        'webmention',
        'Spammer',
        null,
        null,
        'Buy crypto now',
        new \DateTimeImmutable(),
        'spam'
    );

    $saved = $service->ingest($dto, 'spam');
    expect($saved)->toBeTrue();
    expect($this->broadcastCalls)->toBeEmpty();
});

test('ModerationService broadcasts web push alert when interaction is approved', function () {
    $service = new ModerationService($this->tempDir, null, null, $this->mockWebPush);

    // Create a pending notification file
    $content = "---\nauthor: Charlie\nstatus: pending\n---\n\nGreat reading!";
    file_put_contents($this->tempDir . '/microsub/inbox/notifications/pending-123.md', $content);

    $approved = $service->approveInteraction('pending-123', 'pending');
    expect($approved)->toBeTrue();
    expect($this->broadcastCalls)->toHaveCount(1);
    expect($this->broadcastCalls[0]['title'])->toBe('Interaction Approved');
    expect($this->broadcastCalls[0]['body'])->toContain('pending interaction has been approved');
    expect($this->broadcastCalls[0]['url'])->toBe('/microsub/reader?channel=notifications');
    expect($this->broadcastCalls[0]['tag'])->toBe('approved-pending-123');
});

test('ModerationService does not broadcast web push when approval fails', function () {
    $service = new ModerationService($this->tempDir, null, null, $this->mockWebPush);

    $approved = $service->approveInteraction('non-existent-id', 'pending');
    expect($approved)->toBeFalse();
    expect($this->broadcastCalls)->toBeEmpty();
});
