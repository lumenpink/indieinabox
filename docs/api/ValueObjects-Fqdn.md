# Fqdn
**Namespace:** `Indieinabox\ValueObjects`

Immutable Value Object representing a Fully Qualified Domain Name (FQDN).

## Properties

### `private string $value`

## Methods

### __construct()
`public function __construct(string $domain)`

### fromString()
`public static function fromString(string $domain): Indieinabox\ValueObjects\Fqdn`

### normalize()
`private static function normalize(string $domain): string`

### isValid()
`private static function isValid(string $domain): bool`

### toString()
`public function toString(): string`

### getHost()
`public function getHost(): string`

### toUrl()
`public function toUrl(string $scheme = 'https'): string`

### equals()
`public function equals(Indieinabox\ValueObjects\Fqdn|string $other): bool`

### __toString()
`public function __toString(): string`

### jsonSerialize()
`public function jsonSerialize(): string`
