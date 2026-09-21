# Database
**Namespace:** `Indieinabox\Core`

Class Database

Provides a singleton PDO connection to the SQLite database and offers helper
methods for fetching settings, translations, and content configurations.

## Properties

### `private static ?PDO $db`

### `public static ?string $dataDir`

### `private static ?Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settingsRepo`

## Methods

### connect()
`public static function connect(string $path): void`

Connects to the SQLite database and initializes connection attributes.
Sets PRAGMAs for WAL mode and foreign keys for optimized concurrent usage.

@param string $path Path to the SQLite database file.
@throws Exception If PDO extension is missing or connection fails.

### getDb()
`public static function getDb(): PDO`

Retrieves the active PDO database connection.
Throws an exception if the connection has not been established yet.

@return PDO The active PDO instance.
@throws Exception If the database is not connected.

### isConnected()
`public static function isConnected(): bool`

Checks if a database connection is actively open.

### disconnect()
`public static function disconnect(): void`

Closes the active PDO database connection.

### getSettingsRepository()
`public static function getSettingsRepository(): Indieinabox\Repositories\Contracts\SettingsRepositoryInterface`

Resolves the active settings repository.

### setSettingsRepository()
`public static function setSettingsRepository(?Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $repo): void`

Overrides the active settings repository (useful for testing and dependency injection).

### getSetting()
`public static function getSetting(string $key, ?mixed $default = null): ?mixed`

Fetches a single value from the settings table.

@param string $key
@param mixed $default
@return mixed

### saveSetting()
`public static function saveSetting(string $key, ?mixed $value): bool`

Saves a setting value to the database.
Arrays and objects are automatically JSON-encoded.

@param string $key
@param mixed $value
@return bool True on success, false on failure.

### getAllSettings()
`public static function getAllSettings(): array`

Retrieves all rows from the settings table as an associative array.
JSON values are automatically decoded into PHP arrays.

@return array<string, mixed> Key-value pairs of all site settings.

### getTranslations()
`public static function getTranslations(): array`

Fetches interface translations from the database.
Returns an array grouped by phrase key, containing mappings for each language.

@return array<string, array<string, string>> Array of translations.

### getUrlTranslations()
`public static function getUrlTranslations(): array`

Fetches localized URL slugs translations.
Groups results by the internal slug key, mapping it to localized values.

@return array<string, array<string, string>> Array of URL translations.

### getKinds()
`public static function getKinds(): array`

Retrieves content kind configurations (e.g., article, note, photo).
Decodes the JSON configuration column for each kind into an array.

@return array<string, array<string, mixed>> Associative array of kind configs.

### getSchemaSql()
`public static function getSchemaSql(): string`

Retrieves the database schema SQL.

### initializeSchema()
`public static function initializeSchema(?PDO $db = null, ?string $sql = null): void`

Initializes database schema if not already initialized.
