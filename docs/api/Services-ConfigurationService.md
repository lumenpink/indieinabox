# ConfigurationService
**Namespace:** `Indieinabox\Services`

Domain service managing site configuration, kind taxonomies, translations, and theme installations.

## Properties

### `private Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settings`

### `private ?PDO $db`

## Methods

### __construct()
`public function __construct(Indieinabox\Repositories\Contracts\SettingsRepositoryInterface|PDO|null $settings = null, ?PDO $db = null)`

### getSettingsRepository()
`public function getSettingsRepository(): Indieinabox\Repositories\Contracts\SettingsRepositoryInterface`

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

Saves URL slug translations table.

@param array<string, mixed> $urlTranslations

### detectPrettyLinksSupport()
`public function detectPrettyLinksSupport(): bool`

Checks whether the web server environment supports clean pretty links.

### installTheme()
`public function installTheme(array $file, string $themesDir): string`

Validates and installs an uploaded theme zip file.

@param array<string, mixed> $file Uploaded $_FILES entry.
@param string $themesDir Target destination directory.
@return string Installed theme folder name.

### triggerRebuild()
`public function triggerRebuild(?Indieinabox\Site $site = null): void`

Triggers a complete static site generation rebuild.
