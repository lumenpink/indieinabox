# Whostyles
**Namespace:** `Indieinabox\Theme`

Class Whostyles

## Properties

### `private static mixed $ALPHABET_MAP`

@var mixed

## Methods

### getAlphabetMap()
`private static function getAlphabetMap(): array`

Retrieves the custom base-64 alphabet map used for encoding and decoding
whostyle color payloads in URLs.

@return array The alphabet map array.

### decodeBase64()
`public static function decodeBase64(string $str): int`

Decodes a custom base-64 encoded string back into an integer value.

@param string $str The encoded string to decode.
@return int The decoded integer value.

### encodeBase64()
`public static function encodeBase64(int $value, int $length): string`

Method encodeBase64
@param int $value
@param int $length

@return string

### decodeColor()
`public static function decodeColor(string $str): string`

Decodes a 3-character custom base-64 string into a hex color string.

@param string $str The 3-character encoded color string.
@return string The decoded hex color string.

### encodeColor()
`public static function encodeColor(string $hex): string`

Encodes a hex color string into a 3-character custom base-64 string.

@param string $hex The hex color string to encode.
@return string The encoded string.

### decode()
`public static function decode(string $hash): ?array`

Decodes a full Whostyles encoded string into a palette of RGB colors.

@param string $hash The full Whostyles encoded string.
@return array|null Array of RGB color arrays (e.g., background, text, link, etc.), or null on failure.

### encode()
`public static function encode(array $config, array $colors): string`

Method encode
@param array $config
@param array $colors

@return string

### extract()
`public static function extract(string $html): ?string`

Extracts a Whostyles payload from an HTML document or a specific URL.
Searches for a meta tag or specific patterns containing the payload.

@param string $html The HTML content to search.
@return string|null The extracted Whostyles string, or null if not found.

### clean()
`public static function clean(string $html): string`

Cleans HTML string to ensure extracted colors fall within acceptable luminance/contrast bounds
and guarantees a minimum level of legibility (e.g., text against background).

@param string $html The input HTML string containing whostyles.
@return string The cleaned and adjusted HTML string.

### getLuminance()
`private static function getLuminance(string $hex): float`

Calculates the relative luminance of a hex color.

@param string $hex The hex color string.
@return float The relative luminance (0.0 to 1.0).

### getContrast()
`private static function getContrast(string $hex1, string $hex2): float`

Calculates the contrast ratio between two hex colors.

@param string $hex1 The first hex color.
@param string $hex2 The second hex color.
@return float The contrast ratio (1.0 to 21.0).

### generateAttributes()
`public static function generateAttributes(string $hash): string`

Generates a string of HTML data attributes corresponding to a decoded palette.

@param string $hash The encoded Whostyles payload.
@return string A string of HTML data attributes (e.g., `data-bg="#..." data-text="#..."`).
