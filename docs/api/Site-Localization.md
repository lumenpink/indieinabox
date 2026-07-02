# Localization
**Namespace:** `Indieinabox\Site`

Class Localization

Holds language and localization-related configurations.

@property array<string> $lang
@property string $defaultLang

## Properties

### `private array $lang`

/** @var array<string> */

### `private string $defaultLang`

@var string

## Methods

### __construct()
`public function __construct(mixed $lang = null, string $defaultLang = 'en')`

Localization constructor.

@param array<string>|string|number|null $lang
@param string $defaultLang

### __get()
`public function __get(string $name)`

@param string $name
@return array<string>|string|number|null

### __set()
`public function __set(string $name, mixed $value)`

@param string $name
@param array<string>|string|number|null $value

### createArrayFromValue()
`public function createArrayFromValue(mixed $value): array`

Creates an array from a value.

@param array<string>|string|number|null $value
@return array<string>
