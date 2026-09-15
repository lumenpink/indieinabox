# ConfigurationService
**Namespace:** `Indieinabox\Services`

Domain service managing site configuration, kind taxonomies, translations, and theme installations.

## Properties

### `private PDO $db`

## Methods

### __construct()
`public function __construct(?PDO $db = null)`

### bootstrap()
`public function bootstrap(string $password, string $sitename = 'My Site Name', string $fqdn = ''): void`

Initializes a fresh site installation with default configuration and admin credentials.

### getSettings()
`public function getSettings(): array`

Retrieves all configured site settings.

@return array<string, mixed>

### saveSetting()
`public function saveSetting(string $key, ?mixed $value): bool`

Saves or replaces a setting value in the database.

### getKinds()
`public function getKinds(): array`

Retrieves all kind configurations.

@return array<string, mixed>

### saveKinds()
`public function saveKinds(array $kinds): bool`

Saves or replaces kind taxonomies.

@param array<string, mixed> $kinds

### getTranslations()
`public function getTranslations(): array`

Retrieves all language translations.

@return array<string, mixed>

### saveTranslations()
`public function saveTranslations(array $translations): bool`

Saves translations table.

@param array<string, mixed> $translations

### getUrlTranslations()
`public function getUrlTranslations(): array`

Retrieves URL translations.

@return array<string, mixed>

### saveUrlTranslations()
`public function saveUrlTranslations(array $urlTranslations): bool`

Saves URL slug translations.

@param array<string, mixed> $urlTranslations

### detectPrettyLinksSupport()
`public function detectPrettyLinksSupport(): bool`

Detects whether pretty links (clean URLs) are supported by the server environment.

### rebuildSite()
`public function rebuildSite(Indieinabox\Site $site): void`

Triggers static site generation.

### installThemeFromUrl()
`public function installThemeFromUrl(string $url): bool`

Installs a theme from a remote ZIP archive.

### installThemeFromZip()
`public function installThemeFromZip(string $zipPath): bool`

Extracts a theme ZIP archive into the themes directory.
