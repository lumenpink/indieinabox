<?php

declare(strict_types=1);

use Indieinabox\Federation\ActivityPubAdapter;
use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Federation\FederationManager;
use Indieinabox\Site\Site;

test('FederationManager registers default ActivityPub adapter and allows custom adapters', function () {
    $site = new Site();
    $manager = new FederationManager($site);

    expect($manager->has('activitypub'))->toBeTrue();
    expect($manager->has('ap'))->toBeTrue();
    expect($manager->get('activitypub'))->toBeInstanceOf(ActivityPubAdapter::class);

    $mockLemmyAdapter = new class implements FederationAdapter {
        public function getProtocol(): string { return 'lemmy'; }
        public function supports(string $protocol): bool { return strtolower($protocol) === 'lemmy'; }
        public function buildLikeActivity(string $targetUrl): array { return ['type' => 'Like']; }
        public function buildReplyActivity(string $targetUrl, string $content, ?string $inReplyTo = null): array { return []; }
        public function buildFollowActivity(string $targetActorUri): array { return []; }
        public function deliverActivity(array|string $activity, string $destinationUrl): bool { return true; }
        public function parseActivity(string $payload): ?array { return []; }
    };

    $manager->register($mockLemmyAdapter);

    expect($manager->has('lemmy'))->toBeTrue();
    expect($manager->get('lemmy'))->toBe($mockLemmyAdapter);
    expect(count($manager->all()))->toBeGreaterThanOrEqual(2);
});

test('FederationManager throws exception when resolving unregistered protocol', function () {
    $site = new Site();
    $manager = new FederationManager($site);
    $manager->get('unregistered_protocol_xyz');
})->throws(\RuntimeException::class);
