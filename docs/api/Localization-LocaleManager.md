# LocaleManager
**Namespace:** `Indieinabox\Localization`

Service to resolve, download, and apply localized translation dictionaries and taxonomy presets.

## Properties

### `private static array $bundledLocales`

In-memory bundled fallback dictionaries for standalone binary execution and offline environments.

@var array<string, array{code: string, name: string, translations: array<string, string>, kinds: array<string, array{title: string, content_dir: string}>}>

## Methods

### getLocale()
`public static function getLocale(string $lang, bool $allowRemote = true): ?array`

Resolves a locale dictionary for a language code (e.g. 'pt', 'es', 'pt-BR').

@return array{code: string, name: string, translations: array<string, string>, kinds?: array<string, array{title: string, content_dir: string}>}|null

### applyLocale()
`public static function applyLocale(array $config, string $lang, bool $overwriteExisting = false): bool`

Applies locale translations and kind configurations to a settings array in-place.

@param array<string, mixed> $config

### getSupportedLocales()
`public static function getSupportedLocales(): array`

Returns a list of all currently supported locale codes.

@return array<int, string>

### loadLocalOrBundled()
`private static function loadLocalOrBundled(string $code): ?array`

Checks local files and bundled presets.

@return array{code: string, name: string, translations: array<string, string>, kinds?: array<string, array{title: string, content_dir: string}>}|null

### downloadRemote()
`private static function downloadRemote(string $url): ?array`

Downloads and parses a remote JSON file with short timeout.

@return array{code: string, name: string, translations: array<string, string>, kinds?: array<string, array{title: string, content_dir: string}>}|null
