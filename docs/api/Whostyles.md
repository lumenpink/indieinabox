# Whostyles
**Namespace:** `Indieinabox`

## Properties

### `private static mixed $ALPHABET_MAP`

## Methods

### getAlphabetMap()
`private static function getAlphabetMap(): array`

### decodeBase64()
`public static function decodeBase64(string $str): int`

### encodeBase64()
`public static function encodeBase64(int $value, int $length): string`

### decodeColor()
`public static function decodeColor(string $str): string`

### encodeColor()
`public static function encodeColor(string $hex): string`

### decode()
`public static function decode(string $hash): ?array`

### encode()
`public static function encode(array $config, array $colors): string`

### extract()
`public static function extract(string $html): ?string`

### clean()
`public static function clean(string $html): string`

### getLuminance()
`private static function getLuminance(string $hex): float`

### getContrast()
`private static function getContrast(string $hex1, string $hex2): float`

### generateAttributes()
`public static function generateAttributes(string $hash): string`
