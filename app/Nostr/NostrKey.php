<?php

declare(strict_types=1);

namespace Indieinabox\Nostr;

use InvalidArgumentException;

/**
 * Class NostrKey
 *
 * Handles Nostr cryptographic keypair generation, public key derivation,
 * and conversions between 64-character lowercase hex and NIP-19 Bech32 formats (npub/nsec).
 */
class NostrKey
{
    private const BECH32_CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    /**
     * Generates a new cryptographically secure Nostr keypair.
     *
     * @return array{privateKeyHex: string, publicKeyHex: string, nsec: string, npub: string}
     */
    public static function generate(): array
    {
        $rawPriv = random_bytes(32);
        $privHex = bin2hex($rawPriv);
        $pubHex = self::derivePublicKey($privHex);
        $nsec = self::hexToNsec($privHex);
        $npub = self::hexToNpub($pubHex);

        return [
            'privateKeyHex' => $privHex,
            'publicKeyHex' => $pubHex,
            'privHex' => $privHex,
            'pubHex' => $pubHex,
            'nsec' => $nsec,
            'npub' => $npub,
        ];
    }

    /**
     * Derives a 32-byte (64 hex chars) public key from a private key (hex or nsec).
     */
    public static function derivePublicKey(string $privateKeyHexOrNsec): string
    {
        $clean = trim($privateKeyHexOrNsec);
        if (str_starts_with($clean, 'nsec1')) {
            $privHex = self::nsecToHex($clean);
        } else {
            $privHex = strtolower($clean);
        }

        if (strlen($privHex) !== 64 || !ctype_xdigit($privHex)) {
            throw new InvalidArgumentException('Invalid private key format.');
        }

        $rawPriv = (string) hex2bin($privHex);
        $rawPub = Bip340::getPublicKey($rawPriv);

        return bin2hex($rawPub);
    }

    /**
     * Converts a 64-character public key hex to an NIP-19 npub1... string.
     */
    public static function hexToNpub(string $hex): string
    {
        return self::encodeBech32('npub', strtolower(trim($hex)));
    }

    /**
     * Converts an NIP-19 npub1... string to a 64-character lowercase hex.
     */
    public static function npubToHex(string $npub): string
    {
        $hex = self::decodeBech32(trim($npub), 'npub');
        if ($hex === null || strlen($hex) !== 64) {
            throw new InvalidArgumentException('Invalid npub string.');
        }
        return $hex;
    }

    /**
     * Converts a 64-character private key hex to an NIP-19 nsec1... string.
     */
    public static function hexToNsec(string $hex): string
    {
        return self::encodeBech32('nsec', strtolower(trim($hex)));
    }

    /**
     * Converts an NIP-19 nsec1... string to a 64-character lowercase hex.
     */
    public static function nsecToHex(string $nsec): string
    {
        $hex = self::decodeBech32(trim($nsec), 'nsec');
        if ($hex === null || strlen($hex) !== 64) {
            throw new InvalidArgumentException('Invalid nsec string.');
        }
        return $hex;
    }

    /**
     * Converts a 64-character event ID hex to an NIP-19 note1... string.
     */
    public static function hexToNote(string $hex): string
    {
        return self::encodeBech32('note', strtolower(trim($hex)));
    }

    /**
     * Converts an NIP-19 note1... string to a 64-character lowercase hex event ID.
     */
    public static function noteToHex(string $note): string
    {
        $hex = self::decodeBech32(trim($note), 'note');
        if ($hex === null || strlen($hex) !== 64) {
            throw new InvalidArgumentException('Invalid note string.');
        }
        return $hex;
    }


    /**
     * Encodes raw hex bytes into a Bech32 string with specified HRP.
     */
    public static function encodeBech32(string $hrp, string $hex): string
    {
        $bin = hex2bin($hex);
        if ($bin === false) {
            throw new InvalidArgumentException('Invalid hex string.');
        }

        $bytes = array_values(unpack('C*', $bin));
        $data5 = self::convertBits($bytes, 8, 5, true);
        if ($data5 === null) {
            throw new InvalidArgumentException('Failed converting bits for Bech32.');
        }

        $values = array_merge(self::hrpExpand($hrp), $data5, [0, 0, 0, 0, 0, 0]);
        $mod = self::polymod($values) ^ 1;
        $checksum = [];
        for ($i = 0; $i < 6; $i++) {
            $checksum[] = ($mod >> (5 * (5 - $i))) & 31;
        }

        $combined = array_merge($data5, $checksum);
        $res = $hrp . '1';
        foreach ($combined as $val) {
            $res .= self::BECH32_CHARSET[$val];
        }

        return $res;
    }

    /**
     * Decodes a Bech32 string into a lowercase hex string.
     */
    public static function decodeBech32(string $bech32, string $expectedHrp): ?string
    {
        $clean = strtolower(trim($bech32));
        $pos = strrpos($clean, '1');
        if ($pos === false || $pos === 0 || $pos + 7 > strlen($clean)) {
            return null;
        }

        $hrp = substr($clean, 0, $pos);
        if ($hrp !== $expectedHrp) {
            return null;
        }

        $dataChars = substr($clean, $pos + 1);
        $values = [];
        $len = strlen($dataChars);
        for ($i = 0; $i < $len; $i++) {
            $idx = strpos(self::BECH32_CHARSET, $dataChars[$i]);
            if ($idx === false) {
                return null;
            }
            $values[] = $idx;
        }

        if (self::polymod(array_merge(self::hrpExpand($hrp), $values)) !== 1) {
            return null;
        }

        $data5 = array_slice($values, 0, -6);
        $bytes = self::convertBits($data5, 5, 8, false);
        if ($bytes === null) {
            return null;
        }

        return implode('', array_map(fn($b) => sprintf('%02x', $b), $bytes));
    }

    /**
     * Polynomial modulo for Bech32 BCH checksum.
     *
     * @param array<int, int> $values
     */
    private static function polymod(array $values): int
    {
        $gen = [0x3b44d47a, 0x1063e222, 0x21104e8b, 0x11e0e540, 0x05517a24];
        $chk = 1;
        foreach ($values as $v) {
            $b = $chk >> 25;
            $chk = (($chk & 0x1ffffff) << 5) ^ $v;
            for ($i = 0; $i < 5; $i++) {
                if (($b >> $i) & 1) {
                    $chk ^= $gen[$i];
                }
            }
        }
        return $chk;
    }

    /**
     * Expands the HRP string into prefix values for Bech32 checksum.
     *
     * @return array<int, int>
     */
    private static function hrpExpand(string $hrp): array
    {
        $ret = [];
        $len = strlen($hrp);
        for ($i = 0; $i < $len; $i++) {
            $ret[] = ord($hrp[$i]) >> 5;
        }
        $ret[] = 0;
        for ($i = 0; $i < $len; $i++) {
            $ret[] = ord($hrp[$i]) & 31;
        }
        return $ret;
    }

    /**
     * Converts a bit-array between bit bases (e.g. 8-bit to 5-bit or 5-bit to 8-bit).
     *
     * @param array<int, int> $data
     * @return array<int, int>|null
     */
    private static function convertBits(array $data, int $fromBits, int $toBits, bool $pad = true): ?array
    {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;
        $maxAcc = (1 << ($fromBits + $toBits - 1)) - 1;

        foreach ($data as $value) {
            if ($value < 0 || $value >> $fromBits) {
                return null;
            }
            $acc = (($acc << $fromBits) | $value) & $maxAcc;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad) {
            if ($bits) {
                $ret[] = ($acc << ($toBits - $bits)) & $maxv;
            }
        } elseif ($bits >= $fromBits || ((($acc << ($toBits - $bits)) & $maxv))) {
            return null;
        }

        return $ret;
    }
}
