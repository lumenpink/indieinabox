<?php

declare(strict_types=1);

use Indieinabox\Nostr\Contracts\NostrRelayClientInterface;
use Indieinabox\Nostr\NostrKey;
use Indieinabox\Nostr\NostrRelayClient;
use Indieinabox\Nostr\NostrSyndicationService;
use Indieinabox\Page\Page;
use Indieinabox\Site\Site;

it('remains disabled and does not publish when nostr_enabled is false', function () {
    $site = new Site();
    $relaySpy = new class implements NostrRelayClientInterface {
        public int $publishCalls = 0;
        public static function getDefaultRelays(): array { return []; }
        public function buildEventEnvelope(array $eventData): string { return ''; }
        public function parseRelayMessage(string $rawMessage): ?array { return null; }
        public function publish(array $eventData, ?array $relays = null): array {
            $this->publishCalls++;
            return [];
        }
    };

    $service = new NostrSyndicationService($site, $relaySpy);
    $page = Page::fromArray([
        'title' => 'Test Post',
        'content' => 'Hello world',
        'slug' => 'test-post',
    ]);

    $result = $service->syndicate($page);
    expect($result)->toBeNull()
        ->and($relaySpy->publishCalls)->toBe(0);
});

it('formats note content with title, excerpt, and hashtags for Kind 1 event', function () {
    $site = new Site();
    $site->metadata->fqdn = 'https://lumen.pink';

    $service = new NostrSyndicationService($site);
    $page = Page::fromArray([
        'title' => 'Gardening in Cyberspace',
        'content' => 'Exploring digital gardens and indie web protocols.',
        'slug' => 'gardening-cyberspace',
        'tags' => ['indieweb', 'nostr'],
    ]);

    $formatted = $service->formatNoteContent($page);

    expect($formatted)->toContain('Gardening in Cyberspace')
        ->and($formatted)->toContain('https://lumen.pink/gardening-cyberspace/')
        ->and($formatted)->toContain('#indieweb')
        ->and($formatted)->toContain('#nostr');
});

it('publishes Kind 1 event and returns event ID when enabled', function () {
    $site = new Site();
    $site->metadata->fqdn = 'https://lumen.pink';
    $site->config['nostr_enabled'] = true;
    $keypair = NostrKey::generate();
    $site->config['nostr_privkey'] = $keypair['privHex'];
    $site->config['nostr_pubkey'] = $keypair['pubHex'];

    $relaySpy = new class implements NostrRelayClientInterface {
        public int $publishCalls = 0;
        public ?array $lastEvent = null;
        public static function getDefaultRelays(): array { return []; }
        public function buildEventEnvelope(array $eventData): string { return ''; }
        public function parseRelayMessage(string $rawMessage): ?array { return null; }
        public function publish(array $eventData, ?array $relays = null): array {
            $this->publishCalls++;
            $this->lastEvent = $eventData;
            return [
                'wss://relay.damus.io' => ['success' => true, 'message' => 'ok: saved'],
            ];
        }
    };

    $service = new NostrSyndicationService($site, $relaySpy);
    $page = Page::fromArray([
        'title' => 'Federated Note',
        'content' => 'Hello from Indie in a Box to Nostr!',
        'slug' => 'federated-note',
    ]);

    $result = $service->syndicate($page);
    expect($result)->not->toBeNull()
        ->and($result)->toHaveKeys(['id', 'results'])
        ->and($relaySpy->publishCalls)->toBe(1)
        ->and($relaySpy->lastEvent['kind'])->toBe(1)
        ->and($relaySpy->lastEvent['content'])->toContain('Hello from Indie in a Box to Nostr!');
});
