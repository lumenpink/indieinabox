<?php

declare(strict_types=1);

use Indieinabox\Nostr\Bip340;
use Indieinabox\Nostr\NostrEvent;

it('builds, serializes, and signs a NIP-01 Kind 1 event correctly', function () {
    $privKeyHex = '0000000000000000000000000000000000000000000000000000000000000003';
    $pubKeyHex = 'f9308a019258c31049344f85f89d5229b531c845836f99b08601f113bce036f9';
    $createdAt = 1700000000;
    $content = 'Hello Nostr from IndieInABox!';
    $tags = [
        ['t', 'indieweb'],
        ['r', 'https://lumen.pink/'],
    ];

    $event = new NostrEvent(
        pubkey: $pubKeyHex,
        createdAt: $createdAt,
        kind: 1,
        tags: $tags,
        content: $content
    );

    $serialized = $event->serialize();
    expect($serialized)->toBe('[0,"f9308a019258c31049344f85f89d5229b531c845836f99b08601f113bce036f9",1700000000,1,[["t","indieweb"],["r","https://lumen.pink/"]],"Hello Nostr from IndieInABox!"]');

    $id = $event->getId();
    expect($id)->toBe(hash('sha256', $serialized));

    $event->sign($privKeyHex);
    $sig = $event->getSig();
    expect(strlen($sig))->toBe(128); // 64 bytes hex

    // Verify signature using BIP-340
    $msgBytes = hex2bin($id);
    $pubKeyBytes = hex2bin($pubKeyHex);
    $sigBytes = hex2bin($sig);
    expect(Bip340::verify($msgBytes, $pubKeyBytes, $sigBytes))->toBeTrue();

    // To array representation
    $arr = $event->toArray();
    expect($arr)->toHaveKeys(['id', 'pubkey', 'created_at', 'kind', 'tags', 'content', 'sig'])
        ->and($arr['id'])->toBe($id)
        ->and($arr['sig'])->toBe($sig);
});
