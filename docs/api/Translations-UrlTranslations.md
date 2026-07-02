# UrlTranslations
**Namespace:** `Indieinabox\Translations`

Class UrlTranslations

## Properties

### `private mixed $translations`

@var array<string, array<string, string>>

## Methods

### __construct()
`public function __construct(array $translations)`

UrlTranslations constructor.

@param array<string, array<string, string>> $translations

### getTranslatedSlug()
`public function getTranslatedSlug(string $originalContent, string $lang): string`

Get the translated slug for a given original content and language.

@param string $originalContent
@param string $lang
@return string

### getOriginalContent()
`public function getOriginalContent(string $nick, string $defaultLang): string`

Get the original content for a given nick and language.

@param string $nick
@param string $defaultLang
@return string
