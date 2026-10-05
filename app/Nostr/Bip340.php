<?php

declare(strict_types=1);

namespace Indieinabox\Nostr;

use GMP;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class Bip340
 *
 * Implements BIP-340 64-byte Schnorr Signatures over the secp256k1 elliptic curve.
 * Conforms strictly to official Bitcoin Core BIP-340 specification and Nostr NIP-01.
 */
class Bip340
{
    private static ?GMP $p = null;
    private static ?GMP $n = null;
    /** @var array{0: GMP, 1: GMP}|null */
    private static ?array $G = null;

    /**
     * Initializes curve parameters lazily.
     */
    private static function initParams(): void
    {
        if (self::$p === null) {
            self::$p = gmp_init('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16);
            self::$n = gmp_init('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
            $Gx = gmp_init('0x79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798', 16);
            $Gy = gmp_init('0x483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8', 16);
            self::$G = [$Gx, $Gy];
        }
    }

    /**
     * Computes BIP-340 tagged hash: SHA256(SHA256(tag) || SHA256(tag) || msg).
     */
    public static function taggedHash(string $tag, string $msg): string
    {
        $tagHash = hash('sha256', $tag, true);
        return hash('sha256', $tagHash . $tagHash . $msg, true);
    }

    /**
     * Derives the 32-byte x-only public key from a 32-byte secret key.
     *
     * @param string $secKey 32-byte binary string.
     * @return string 32-byte binary string (x-coordinate).
     */
    public static function getPublicKey(string $secKey): string
    {
        self::initParams();
        if (strlen($secKey) !== 32) {
            throw new InvalidArgumentException('Secret key must be exactly 32 bytes.');
        }

        $d0 = gmp_init(bin2hex($secKey), 16);
        if (gmp_cmp($d0, gmp_init(1)) < 0 || gmp_cmp($d0, gmp_sub(self::$n, gmp_init(1))) > 0) {
            throw new InvalidArgumentException('Secret key out of valid secp256k1 range [1..n-1].');
        }

        $P = self::pointMul(self::$G, $d0);
        if ($P === null) {
            throw new RuntimeException('Failed to derive public key point.');
        }

        return self::bytesFromInt($P[0]);
    }

    /**
     * Signs a 32-byte message hash using BIP-340 Schnorr signature.
     *
     * @param string $msg 32-byte binary message hash.
     * @param string $secKey 32-byte binary secret key.
     * @param string|null $auxRand Optional 32-byte auxiliary random bytes (defaults to random_bytes(32)).
     * @return string 64-byte binary signature (R_x || s).
     */
    public static function sign(string $msg, string $secKey, ?string $auxRand = null): string
    {
        self::initParams();
        if (strlen($msg) !== 32) {
            throw new InvalidArgumentException('Message must be 32 bytes.');
        }
        if (strlen($secKey) !== 32) {
            throw new InvalidArgumentException('Secret key must be 32 bytes.');
        }

        $auxRand = $auxRand ?? random_bytes(32);
        if (strlen($auxRand) !== 32) {
            throw new InvalidArgumentException('Auxiliary randomness must be 32 bytes.');
        }

        $d0 = gmp_init(bin2hex($secKey), 16);
        if (gmp_cmp($d0, gmp_init(1)) < 0 || gmp_cmp($d0, gmp_sub(self::$n, gmp_init(1))) > 0) {
            throw new InvalidArgumentException('Secret key out of valid secp256k1 range.');
        }

        $P = self::pointMul(self::$G, $d0);
        if ($P === null) {
            throw new RuntimeException('Point multiplication failed.');
        }

        $hasEvenY = gmp_cmp(gmp_mod($P[1], gmp_init(2)), gmp_init(0)) === 0;
        $d = $hasEvenY ? $d0 : gmp_sub(self::$n, $d0);

        $t = self::bytesFromInt($d) ^ self::taggedHash('BIP0340/aux', $auxRand);
        $k0 = gmp_mod(gmp_init(bin2hex(self::taggedHash('BIP0340/nonce', $t . self::bytesFromInt($P[0]) . $msg)), 16), self::$n);
        if (gmp_cmp($k0, gmp_init(0)) === 0) {
            throw new RuntimeException('k0 is zero; nonce generation failed.');
        }

        $R = self::pointMul(self::$G, $k0);
        if ($R === null) {
            throw new RuntimeException('Failed computing R point.');
        }

        $hasEvenYR = gmp_cmp(gmp_mod($R[1], gmp_init(2)), gmp_init(0)) === 0;
        $k = $hasEvenYR ? $k0 : gmp_sub(self::$n, $k0);

        $e = gmp_mod(
            gmp_init(bin2hex(self::taggedHash('BIP0340/challenge', self::bytesFromInt($R[0]) . self::bytesFromInt($P[0]) . $msg)), 16),
            self::$n
        );

        $s = gmp_mod(gmp_add($k, gmp_mul($e, $d)), self::$n);

        return self::bytesFromInt($R[0]) . self::bytesFromInt($s);
    }

    /**
     * Verifies a 64-byte BIP-340 Schnorr signature against a 32-byte message and 32-byte public key.
     *
     * @param string $msg 32-byte binary message hash.
     * @param string $pubKey 32-byte binary public key (x-only).
     * @param string $sig 64-byte binary signature.
     * @return bool True if valid, false otherwise.
     */
    public static function verify(string $msg, string $pubKey, string $sig): bool
    {
        self::initParams();
        if (strlen($msg) !== 32 || strlen($pubKey) !== 32 || strlen($sig) !== 64) {
            return false;
        }

        $P = self::liftX(gmp_init(bin2hex($pubKey), 16));
        $r = gmp_init(bin2hex(substr($sig, 0, 32)), 16);
        $s = gmp_init(bin2hex(substr($sig, 32, 32)), 16);

        if ($P === null || gmp_cmp($r, self::$p) >= 0 || gmp_cmp($s, self::$n) >= 0) {
            return false;
        }

        $e = gmp_mod(
            gmp_init(bin2hex(self::taggedHash('BIP0340/challenge', substr($sig, 0, 32) . $pubKey . $msg)), 16),
            self::$n
        );

        // R = s*G - e*P = s*G + (n - e)*P
        $sG = self::pointMul(self::$G, $s);
        $negEP = self::pointMul($P, gmp_sub(self::$n, $e));
        $R = self::pointAdd($sG, $negEP);

        if ($R === null) {
            return false;
        }

        $hasEvenYR = gmp_cmp(gmp_mod($R[1], gmp_init(2)), gmp_init(0)) === 0;
        if (!$hasEvenYR || gmp_cmp($R[0], $r) !== 0) {
            return false;
        }

        return true;
    }

    /**
     * Point addition on secp256k1 in affine coordinates.
     *
     * @param array{0: GMP, 1: GMP}|null $p1
     * @param array{0: GMP, 1: GMP}|null $p2
     * @return array{0: GMP, 1: GMP}|null
     */
    private static function pointAdd(?array $p1, ?array $p2): ?array
    {
        if ($p1 === null) {
            return $p2;
        }
        if ($p2 === null) {
            return $p1;
        }

        [$x1, $y1] = $p1;
        [$x2, $y2] = $p2;

        if (gmp_cmp($x1, $x2) === 0 && gmp_cmp($y1, $y2) !== 0) {
            return null;
        }

        if (gmp_cmp($x1, $x2) === 0 && gmp_cmp($y1, $y2) === 0) {
            $num = gmp_mul(gmp_init(3), gmp_mul($x1, $x1));
            $den = gmp_powm(gmp_mul(gmp_init(2), $y1), gmp_sub(self::$p, gmp_init(2)), self::$p);
            $lam = gmp_mod(gmp_mul($num, $den), self::$p);
        } else {
            $num = gmp_sub($y2, $y1);
            $den = gmp_powm(gmp_sub($x2, $x1), gmp_sub(self::$p, gmp_init(2)), self::$p);
            $lam = gmp_mod(gmp_mul($num, $den), self::$p);
        }

        $x3 = gmp_mod(gmp_sub(gmp_sub(gmp_mul($lam, $lam), $x1), $x2), self::$p);
        if (gmp_sign($x3) < 0) {
            $x3 = gmp_add($x3, self::$p);
        }

        $y3 = gmp_mod(gmp_sub(gmp_mul($lam, gmp_sub($x1, $x3)), $y1), self::$p);
        if (gmp_sign($y3) < 0) {
            $y3 = gmp_add($y3, self::$p);
        }

        return [$x3, $y3];
    }

    /**
     * Point scalar multiplication on secp256k1.
     *
     * @param array{0: GMP, 1: GMP}|null $P
     * @param GMP $d
     * @return array{0: GMP, 1: GMP}|null
     */
    private static function pointMul(?array $P, GMP $d): ?array
    {
        $R = null;
        for ($i = 0; $i < 256; $i++) {
            if (gmp_testbit($d, $i)) {
                $R = self::pointAdd($R, $P);
            }
            $P = self::pointAdd($P, $P);
        }
        return $R;
    }

    /**
     * Lifts an x-coordinate to a point P on secp256k1 with an even y-coordinate.
     *
     * @param GMP $x
     * @return array{0: GMP, 1: GMP}|null
     */
    private static function liftX(GMP $x): ?array
    {
        if (gmp_cmp($x, self::$p) >= 0) {
            return null;
        }

        // y^2 = x^3 + 7 mod p
        $ySq = gmp_mod(gmp_add(gmp_powm($x, gmp_init(3), self::$p), gmp_init(7)), self::$p);
        $y = gmp_powm($ySq, gmp_div_q(gmp_add(self::$p, gmp_init(1)), gmp_init(4)), self::$p);

        if (gmp_cmp(gmp_powm($y, gmp_init(2), self::$p), $ySq) !== 0) {
            return null;
        }

        $isEven = gmp_cmp(gmp_mod($y, gmp_init(2)), gmp_init(0)) === 0;
        return [$x, $isEven ? $y : gmp_sub(self::$p, $y)];
    }

    /**
     * Converts a GMP integer to 32 big-endian bytes.
     */
    private static function bytesFromInt(GMP $x): string
    {
        $hex = str_pad(gmp_strval($x, 16), 64, '0', STR_PAD_LEFT);
        return (string) hex2bin($hex);
    }
}
