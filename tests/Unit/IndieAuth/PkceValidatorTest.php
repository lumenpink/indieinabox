<?php

declare(strict_types=1);

use Indieinabox\IndieAuth\PkceValidator;

test('PkceValidator validates plain code challenge method', function () {
    $verifier = 'high-entropy-cryptographic-random-string-12345';
    expect(PkceValidator::validate($verifier, $verifier, 'plain'))->toBeTrue();
    expect(PkceValidator::validate($verifier, 'wrong-verifier', 'plain'))->toBeFalse();
});

test('PkceValidator calculates S256 challenge correctly per RFC 7636', function () {
    // RFC 7636 Appendix B test vector
    $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
    $expectedChallenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

    $calculated = PkceValidator::calculateChallenge($verifier, 'S256');
    expect($calculated)->toBe($expectedChallenge);
    expect(PkceValidator::validate($verifier, $expectedChallenge, 'S256'))->toBeTrue();
    expect(PkceValidator::validate('wrong-verifier', $expectedChallenge, 'S256'))->toBeFalse();
});

test('PkceValidator base64UrlEncode strips padding and replaces characters', function () {
    $raw = "\xfb\xff\xfe";
    $encoded = PkceValidator::base64UrlEncode($raw);
    expect($encoded)->not->toContain('+')
        ->not->toContain('/')
        ->not->toContain('=');
});
