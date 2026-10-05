<?php

declare(strict_types=1);

use Indieinabox\Nostr\Bip340;

it('computes BIP-340 tagged hashes correctly', function () {
    $tag = 'BIP0340/challenge';
    $msg = 'hello world';
    $hash = Bip340::taggedHash($tag, $msg);

    expect(strlen($hash))->toBe(32);
    // Deterministic verify
    $expectedTagHash = hash('sha256', $tag, true);
    $expected = hash('sha256', $expectedTagHash . $expectedTagHash . $msg, true);
    expect($hash)->toBe($expected);
});

it('derives secp256k1 public key matching BIP-340 Vector 0', function () {
    // BIP-340 Vector 0 secret key: 0x03
    $secKey = str_pad(hex2bin('03'), 32, "\x00", STR_PAD_LEFT);
    $pubKey = Bip340::getPublicKey($secKey);

    $expectedHex = 'f9308a019258c31049344f85f89d5229b531c845836f99b08601f113bce036f9';
    expect(bin2hex($pubKey))->toBe($expectedHex);
});

it('signs and verifies a message matching BIP-340 reference vector', function () {
    $secKey = str_pad(hex2bin('03'), 32, "\x00", STR_PAD_LEFT);
    $msg = str_repeat("\x00", 32);
    $auxRand = str_repeat("\x00", 32);

    $pubKey = Bip340::getPublicKey($secKey);
    $sig = Bip340::sign($msg, $secKey, $auxRand);

    expect(strlen($sig))->toBe(64);
    $expectedSig = 'e907831f80848d1069a5371b402410364bdf1c5f8307b0084c55f1ce2dca821525f66a4a85ea8b71e482a74f382d2ce5ebeee8fdb2172f477df4900d310536c0';
    expect(bin2hex($sig))->toBe($expectedSig);

    // Verification
    $valid = Bip340::verify($msg, $pubKey, $sig);
    expect($valid)->toBeTrue();
});

it('rejects tampered signatures or messages', function () {
    $secKey = str_pad(hex2bin('03'), 32, "\x00", STR_PAD_LEFT);
    $msg = str_repeat("\x00", 32);
    $pubKey = Bip340::getPublicKey($secKey);
    $sig = Bip340::sign($msg, $secKey);

    // Tampered message
    $tamperedMsg = str_repeat("\x01", 32);
    expect(Bip340::verify($tamperedMsg, $pubKey, $sig))->toBeFalse();

    // Tampered signature
    $tamperedSig = $sig;
    $tamperedSig[0] = chr(ord($tamperedSig[0]) ^ 1);
    expect(Bip340::verify($msg, $pubKey, $tamperedSig))->toBeFalse();
});
