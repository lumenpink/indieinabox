# Slug
**Namespace:** `Indieinabox\ValueObjects`

Immutable Value Object representing a clean URL slug.

## Properties

### `private string $value`

## Methods

### __construct()
`public function __construct(string $slug)`

### fromString()
`public static function fromString(string $slug): Indieinabox\ValueObjects\Slug`

### fromTitle()
`public static function fromTitle(string $title): Indieinabox\ValueObjects\Slug`

Generates a Slug from any raw text or title.

### isValid()
`private static function isValid(string $slug): bool`

### toString()
`public function toString(): string`

### equals()
`public function equals(Indieinabox\ValueObjects\Slug|string $other): bool`

### __toString()
`public function __toString(): string`

### jsonSerialize()
`public function jsonSerialize(): string`
