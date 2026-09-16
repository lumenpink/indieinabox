<?php

declare(strict_types=1);

use Indieinabox\ValueObjects\AuthToken;
use Indieinabox\ValueObjects\Fqdn;
use Indieinabox\ValueObjects\Slug;

describe('Fqdn Value Object', function () {
    it('normalizes domain names by removing scheme, port, paths, and converting to lowercase', function () {
        $fqdn1 = new Fqdn('https://MyDomain.ORG:8080/path/to/page');
        expect($fqdn1->toString())->toBe('mydomain.org');
        expect($fqdn1->getHost())->toBe('mydomain.org');
        expect((string) $fqdn1)->toBe('mydomain.org');
        expect($fqdn1->toUrl())->toBe('https://mydomain.org');
        expect($fqdn1->toUrl('http'))->toBe('http://mydomain.org');

        $fqdn2 = Fqdn::fromString('localhost');
        expect($fqdn2->toString())->toBe('localhost');

        $fqdn3 = Fqdn::fromString('127.0.0.1');
        expect($fqdn3->toString())->toBe('127.0.0.1');
    });

    it('rejects invalid domain names with InvalidArgumentException', function () {
        expect(fn() => new Fqdn(''))->toThrow(InvalidArgumentException::class);
        expect(fn() => new Fqdn('   '))->toThrow(InvalidArgumentException::class);
        expect(fn() => new Fqdn('invalid..domain'))->toThrow(InvalidArgumentException::class);
        expect(fn() => new Fqdn('-badprefix.com'))->toThrow(InvalidArgumentException::class);
    });

    it('compares equality correctly and serializes to JSON', function () {
        $fqdn1 = new Fqdn('https://indieweb.org');
        $fqdn2 = new Fqdn('INDIEWEB.ORG');
        $fqdn3 = new Fqdn('example.com');

        expect($fqdn1->equals($fqdn2))->toBeTrue();
        expect($fqdn1->equals('https://indieweb.org/some/page'))->toBeTrue();
        expect($fqdn1->equals($fqdn3))->toBeFalse();

        expect(json_encode($fqdn1))->toBe('"indieweb.org"');
    });
});

describe('Slug Value Object', function () {
    it('validates and formats valid slugs', function () {
        $slug = new Slug('hello-world-2026');
        expect($slug->toString())->toBe('hello-world-2026');
        expect((string) $slug)->toBe('hello-world-2026');
        expect(json_encode($slug))->toBe('"hello-world-2026"');
    });

    it('creates clean slugs from arbitrary titles with diacritics', function () {
        $slug = Slug::fromTitle('Atenção aos Detalhes: Café & Ação 2026!');
        expect($slug->toString())->toBe('atencao-aos-detalhes-cafe-acao-2026');
    });

    it('rejects invalid slugs with InvalidArgumentException', function () {
        expect(fn() => new Slug(''))->toThrow(InvalidArgumentException::class);
        expect(fn() => new Slug('hello--world'))->toThrow(InvalidArgumentException::class);
        expect(fn() => new Slug('invalid_slug_with_underscores'))->toThrow(InvalidArgumentException::class);
        expect(fn() => new Slug('invalid slug with spaces'))->toThrow(InvalidArgumentException::class);
    });

    it('compares equality ignoring leading or trailing slashes and case', function () {
        $slug1 = new Slug('my-first-post');
        $slug2 = Slug::fromString('MY-FIRST-POST');
        $slug3 = new Slug('other-post');

        expect($slug1->equals($slug2))->toBeTrue();
        expect($slug1->equals('/my-first-post/'))->toBeTrue();
        expect($slug1->equals($slug3))->toBeFalse();
    });
});

describe('AuthToken Value Object', function () {
    it('normalizes tokens by stripping Bearer prefix and whitespace', function () {
        $token = new AuthToken('Bearer secret-api-token-12345678');
        expect($token->toString())->toBe('secret-api-token-12345678');
        expect($token->toHeader())->toBe('Bearer secret-api-token-12345678');
        expect((string) $token)->toBe('secret-api-token-12345678');
        expect(json_encode($token))->toBe('"secret-api-token-12345678"');
    });

    it('generates cryptographically secure random tokens', function () {
        $token = AuthToken::generate(16);
        expect(strlen($token->toString()))->toBe(32); // 16 bytes = 32 hex chars
    });

    it('masks token safely for logs and computes hash', function () {
        $token = new AuthToken('abcdef1234567890xyz');
        expect($token->toMaskedString(4))->toBe('abcd...0xyz');
        expect($token->hash('sha256'))->toBe(hash('sha256', 'abcdef1234567890xyz'));
    });

    it('performs timing-safe equality comparison', function () {
        $token1 = new AuthToken('secret-token-abcdef123');
        $token2 = new AuthToken('Bearer secret-token-abcdef123');
        $token3 = new AuthToken('different-token-9876543');

        expect($token1->equals($token2))->toBeTrue();
        expect($token1->equals('Bearer secret-token-abcdef123'))->toBeTrue();
        expect($token1->equals($token3))->toBeFalse();
    });

    it('rejects short or empty tokens', function () {
        expect(fn() => new AuthToken(''))->toThrow(InvalidArgumentException::class);
        expect(fn() => new AuthToken('short'))->toThrow(InvalidArgumentException::class);
    });
});
