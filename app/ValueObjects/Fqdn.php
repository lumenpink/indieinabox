<?php

declare(strict_types=1);

namespace Indieinabox\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable Value Object representing a Fully Qualified Domain Name (FQDN).
 */
final class Fqdn implements Stringable, JsonSerializable
{
    private string $value;

    public function __construct(string $domain)
    {
        $normalized = self::normalize($domain);
        if (!self::isValid($normalized)) {
            throw new InvalidArgumentException("Invalid domain name: [{$domain}]");
        }
        $this->value = $normalized;
    }

    public static function fromString(string $domain): self
    {
        return new self($domain);
    }

    private static function normalize(string $domain): string
    {
        $domain = trim($domain);
        // Remove scheme if present
        if (preg_match('#^https?://#i', $domain)) {
            $parsed = parse_url($domain, PHP_URL_HOST);
            if ($parsed !== null && $parsed !== false) {
                $domain = $parsed;
            } else {
                $domain = preg_replace('#^https?://#i', '', $domain) ?? '';
            }
        }

        // Remove trailing slashes and port
        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];

        return strtolower(trim($domain));
    }

    private static function isValid(string $domain): bool
    {
        if ($domain === '' || strlen($domain) > 253) {
            return false;
        }

        if ($domain === 'localhost') {
            return true;
        }

        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Standard FQDN regex
        $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i';
        return (bool) preg_match($pattern, $domain);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function getHost(): string
    {
        return $this->value;
    }

    public function toUrl(string $scheme = 'https'): string
    {
        return rtrim($scheme, ':/') . '://' . $this->value;
    }

    public function equals(self|string $other): bool
    {
        $otherVal = $other instanceof self ? $other->value : self::normalize($other);
        return $this->value === $otherVal;
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
