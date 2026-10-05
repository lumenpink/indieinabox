<?php

declare(strict_types=1);

use Indieinabox\Nostr\NostrKey;

it('generates a valid 32-byte private key and derives public key', function () {
    $pair = NostrKey::generate();

    expect($pair)->toHaveKeys(['privateKeyHex', 'publicKeyHex', 'nsec', 'npub'])
        ->and(strlen($pair['privateKeyHex']))->toBe(64)
        ->and(strlen($pair['publicKeyHex']))->toBe(64)
        ->and(str_starts_with($pair['nsec'], 'nsec1'))->toBeTrue()
        ->and(str_starts_with($pair['npub'], 'npub1'))->toBeTrue();
});

it('converts between Hex and Bech32 encodings accurately (NIP-19)', function () {
    // Known test vector
    $hex = '63b057107b4f2ac7f7ddc0bb574e97f8320fd865676329ce84e7ed455c57211e';
    $npub = NostrKey::hexToNpub($hex);

    expect(str_starts_with($npub, 'npub1'))->toBeTrue();
    $decodedHex = NostrKey::npubToHex($npub);
    expect($decodedHex)->toBe($hex);

    $privHex = 'cf68698602b9726b6f914b5446c5a30cc519256e43c43a40b6bf23b11dbcd143';
    $nsec = NostrKey::hexToNsec($privHex);
    expect(str_starts_with($nsec, 'nsec1'))->toBeTrue();
    $decodedPriv = NostrKey::nsecToHex($nsec);
    expect($decodedPriv)->toBe($privHex);
});

it('derives public key from an existing private key hex or nsec', function () {
    $privHex = '0000000000000000000000000000000000000000000000000000000000000003';
    $pubHex = NostrKey::derivePublicKey($privHex);
    expect($pubHex)->toBe('f9308a019258c31049344f85f89d5229b531c845836f99b08601f113bce036f9');

    $nsec = NostrKey::hexToNsec($privHex);
    $pubHexFromNsec = NostrKey::derivePublicKey($nsec);
    expect($pubHexFromNsec)->toBe('f9308a019258c31049344f85f89d5229b531c845836f99b08601f113bce036f9');
});
