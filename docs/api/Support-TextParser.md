# TextParser
**Namespace:** `Indieinabox\Support`

Class TextParser

Provides string normalization, ASCII transliteration, slug generation,
array access utilities, and hashtag extraction.

## Methods

### arrayGet()
`public static function arrayGet(array $array, string $key, ?mixed $default = null): ?mixed`

Safely retrieves a key from an array with a fallback default.

@param array<string, mixed> $array
@param string $key
@param mixed $default
@return mixed

### extractHashtags()
`public static function extractHashtags(string $text): array`

Extracts hashtags from a given text string.

@param string $text The post text
@return array<string> List of unique hashtags without the # symbol

### unaccent()
`public static function unaccent(string $string): string`

Removes accents from a string using iconv and transliteration.

@param string $string
@return string

### utf8ToAscii()
`public static function utf8ToAscii(string $str, string $unknown = '?'): string`

Converts a UTF-8 string to ASCII.

@param string $str
@param string $unknown
@return string

### decodeUtf8Codepoint()
`private static function decodeUtf8Codepoint(string $c): int`

Decodes a multi-byte UTF-8 character sequence into its Unicode codepoint.

@param string $c
@return int

### loadUtf8Bank()
`private static function loadUtf8Bank(int $bank, array $cache): void`

Lazily loads a UTF-8 translation bank.

@param int $bank
@param array<int, array<int, string>> $cache
@return void

### slugize()
`public static function slugize(string $str): string`

Converts a string into a clean, URL-safe slug.

@param string $str
@return string
