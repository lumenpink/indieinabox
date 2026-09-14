# NormalizationAdapter
**Namespace:** `Indieinabox\Microsub`

Normalization Adapter

Takes parsed data from various networks (ActivityPub, Twtxt, RSS)
and normalizes it into a Universal Post Object (ExtendedEntry).

## Methods

### fromActivityPub()
`public static function fromActivityPub(array $json, string $htmlContent): Indieinabox\Microsub\ExtendedEntry`

Creates an ExtendedEntry from an ActivityPub JSON object.

### fromTwtxt()
`public static function fromTwtxt(string $uid, string $url, string $text, int $timestamp, string $authorName): Indieinabox\Microsub\ExtendedEntry`

Creates an ExtendedEntry from Twtxt data.

### fromFeed()
`public static function fromFeed(string $uid, string $url, string $htmlContent, int $timestamp, string $authorName, string $feedUrl): Indieinabox\Microsub\ExtendedEntry`

Creates an ExtendedEntry from standard Feed items (RSS/Atom/JSONFeed).
