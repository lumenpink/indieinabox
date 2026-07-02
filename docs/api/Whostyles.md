# Whostyles
**Namespace:** `Indieinabox`

Class Whostyles

## Properties

### `private static mixed $ALPHABET_MAP`

@var mixed

## Methods

### getAlphabetMap()
`private static function getAlphabetMap(): array`

Method getAlphabetMap
@return array

### decodeBase64()
`public static function decodeBase64(string $str): int`

Method decodeBase64
@param string $str

@return int

### encodeBase64()
`public static function encodeBase64(int $value, int $length): string`

Method encodeBase64
@param int $value
@param int $length

@return string

### decodeColor()
`public static function decodeColor(string $str): string`

Method decodeColor
@param string $str

@return string

### encodeColor()
`public static function encodeColor(string $hex): string`

Method encodeColor
@param string $hex

@return string

### decode()
`public static function decode(string $hash): ?array`

Method decode
@param string $hash

@return ?array

### encode()
`public static function encode(array $config, array $colors): string`

Method encode
@param array $config
@param array $colors

@return string

### extract()
`public static function extract(string $html): ?string`

Method extract
@param string $html

@return ?string

### clean()
`public static function clean(string $html): string`

Method clean
@param string $html

@return string

### getLuminance()
`private static function getLuminance(string $hex): float`

Method getLuminance
@param string $hex

@return float

### getContrast()
`private static function getContrast(string $hex1, string $hex2): float`

Method getContrast
@param string $hex1
@param string $hex2

@return float

### generateAttributes()
`public static function generateAttributes(string $hash): string`

Method generateAttributes
@param string $hash

@return string
