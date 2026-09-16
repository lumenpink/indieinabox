# Translator
**Namespace:** `Indieinabox\Localization`

Class Translator

Handles i18n translation lookups, pluralization, database synchronization,
and slugized translation transformations.

## Methods

### translate()
`public static function translate(string $text, ?string $lang = null): string`

Translation lookup

@param string $text
@param string|null $lang
@return string

### translatePlural()
`public static function translatePlural(string $singular, string $plural, int $count, ?string $lang = null): string`

Translation lookup with pluralization support

@param string $singular
@param string $plural
@param int $count
@param string|null $lang
@return string

### translateLowercase()
`public static function translateLowercase(string $text): string`

Translate and make lowercase

@param string $text
@return string

### translateSlugize()
`public static function translateSlugize(string $text): string`

Translate and slugize

@param string $text
@return string

### updateTranslations()
`public static function updateTranslations(?array $translations = null): void`

Update translations file / database

@return void
