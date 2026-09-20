<?php

declare(strict_types=1);

namespace Indieinabox\ValueObjects;

use Indieinabox\Support\TextParser;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable Value Object representing a clean URL slug.
 */
final class Slug implements Stringable, JsonSerializable
{
    private string $value;

    public function __construct(string $slug)
    {
        $normalized = trim($slug, " \t\n\r\0\x0B/-");
        $normalized = strtolower($normalized);

        if (!self::isValid($normalized)) {
            throw new InvalidArgumentException("Invalid slug format: [{$slug}]");
        }

        $this->value = $normalized;
    }

    public static function fromString(string $slug): self
    {
        return new self($slug);
    }

    /**
     * Generates a Slug from any raw text or title.
     */
    public static function fromTitle(string $title): self
    {
        $slugized = TextParser::slugize($title);
        $slugized = (string) preg_replace('/-+/', '-', $slugized);
        $slugized = trim($slugized, '-');

        if ($slugized === '') {
            throw new InvalidArgumentException("Cannot generate slug from empty or invalid title: [{$title}]");
        }

        return new self($slugized);
    }

    private static function isValid(string $slug): bool
    {
        if ($slug === '' || strlen($slug) > 200) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self|string $other): bool
    {
        $otherVal = $other instanceof self ? $other->value : trim((string) $other, " \t\n\r\0\x0B/-");
        return $this->value === strtolower($otherVal);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
