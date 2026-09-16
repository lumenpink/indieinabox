# AuthToken
**Namespace:** `Indieinabox\ValueObjects`

Immutable Value Object representing a Bearer / Authentication Token.

## Properties

### `private string $value`

## Methods

### __construct()
`public function __construct(string $token)`

### fromString()
`public static function fromString(string $token): Indieinabox\ValueObjects\AuthToken`

### generate()
`public static function generate(int $bytes = 32): Indieinabox\ValueObjects\AuthToken`

Generates a cryptographically secure random token.

### normalize()
`private static function normalize(string $token): string`

### isValid()
`private static function isValid(string $token): bool`

### toString()
`public function toString(): string`

### toHeader()
`public function toHeader(): string`

### toMaskedString()
`public function toMaskedString(int $visibleChars = 4): string`

Returns a masked representation suitable for logs and audit trails (e.g. "abcd...wxyz").

### hash()
`public function hash(string $algo = 'sha256'): string`

Computes a cryptographic hash of the token.

### equals()
`public function equals(Indieinabox\ValueObjects\AuthToken|string $other): bool`

Constant-time equality comparison to prevent timing attacks.

### __toString()
`public function __toString(): string`

### jsonSerialize()
`public function jsonSerialize(): string`
