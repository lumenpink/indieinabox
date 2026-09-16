<?php

declare(strict_types=1);

namespace Indieinabox\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable Value Object representing a Bearer / Authentication Token.
 */
final class AuthToken implements Stringable, JsonSerializable
{
    private string $value;

    public function __construct(string $token)
    {
        $normalized = self::normalize($token);
        if (!self::isValid($normalized)) {
            throw new InvalidArgumentException("Invalid authentication token format.");
        }
        $this->value = $normalized;
    }

    public static function fromString(string $token): self
    {
        return new self($token);
    }

    /**
     * Generates a cryptographically secure random token.
     */
    public static function generate(int $bytes = 32): self
    {
        return new self(bin2hex(random_bytes($bytes)));
    }

    private static function normalize(string $token): string
    {
        $token = trim($token);
        if (stripos($token, 'Bearer ') === 0) {
            $token = trim(substr($token, 7));
        }
        return $token;
    }

    private static function isValid(string $token): bool
    {
        if (strlen($token) < 8 || strlen($token) > 1024) {
            return false;
        }

        // Compliant with RFC 6750 b64token / safe token character set
        return (bool) preg_match('/^[a-zA-Z0-9\-\._~\+\/]+=*$/', $token);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function toHeader(): string
    {
        return 'Bearer ' . $this->value;
    }

    /**
     * Returns a masked representation suitable for logs and audit trails (e.g. "abcd...wxyz").
     */
    public function toMaskedString(int $visibleChars = 4): string
    {
        $length = strlen($this->value);
        if ($length <= ($visibleChars * 2)) {
            return str_repeat('*', $length);
        }

        return substr($this->value, 0, $visibleChars) . '...' . substr($this->value, -$visibleChars);
    }

    /**
     * Computes a cryptographic hash of the token.
     */
    public function hash(string $algo = 'sha256'): string
    {
        return hash($algo, $this->value);
    }

    /**
     * Constant-time equality comparison to prevent timing attacks.
     */
    public function equals(self|string $other): bool
    {
        $otherVal = $other instanceof self ? $other->value : self::normalize($other);
        return hash_equals($this->value, $otherVal);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
