<?php

declare(strict_types=1);

use Indieinabox\Nostr\NostrRelayClient;

it('provides default public relays with active flags', function () {
    $defaults = NostrRelayClient::getDefaultRelays();

    expect($defaults)->toBeArray()
        ->and(count($defaults))->toBeGreaterThanOrEqual(4);

    $urls = array_column($defaults, 'url');
    expect($urls)->toContain('wss://relay.damus.io')
        ->and($urls)->toContain('wss://nos.lol')
        ->and($urls)->toContain('wss://relay.nostr.band')
        ->and($urls)->toContain('wss://nostr.mom');

    foreach ($defaults as $relay) {
        expect($relay)->toHaveKeys(['url', 'active'])
            ->and($relay['active'])->toBeTrue();
    }
});

it('formats NIP-01 WebSocket EVENT envelope accurately', function () {
    $client = new NostrRelayClient();
    $eventData = [
        'id' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
        'pubkey' => 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
        'created_at' => 1700000000,
        'kind' => 1,
        'tags' => [],
        'content' => 'Test event',
        'sig' => 'fedcba0987654321',
    ];

    $envelope = $client->buildEventEnvelope($eventData);
    expect($envelope)->toBe('["EVENT",{"id":"1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef","pubkey":"abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890","created_at":1700000000,"kind":1,"tags":[],"content":"Test event","sig":"fedcba0987654321"}]');
});

it('parses NIP-01 OK and NOTICE relay responses', function () {
    $client = new NostrRelayClient();

    // Valid OK response
    $okJson = '["OK", "b1a0...1234", true, ""]';
    $parsedOk = $client->parseRelayMessage($okJson);
    expect($parsedOk)->toBe([
        'type' => 'OK',
        'eventId' => 'b1a0...1234',
        'success' => true,
        'message' => '',
    ]);

    // Rejected OK response
    $rejectedJson = '["OK", "b1a0...1234", false, "blocked: spam filter"]';
    $parsedRejected = $client->parseRelayMessage($rejectedJson);
    expect($parsedRejected)->toBe([
        'type' => 'OK',
        'eventId' => 'b1a0...1234',
        'success' => false,
        'message' => 'blocked: spam filter',
    ]);

    // Notice response
    $noticeJson = '["NOTICE", "rate limit exceeded"]';
    $parsedNotice = $client->parseRelayMessage($noticeJson);
    expect($parsedNotice)->toBe([
        'type' => 'NOTICE',
        'message' => 'rate limit exceeded',
    ]);
});
