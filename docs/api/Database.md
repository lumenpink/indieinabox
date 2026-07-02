# Database
**Namespace:** `Indieinabox`

## Properties

### `private static ?PDO $db`

### `public static ?string $dataDir`

## Methods

### connect()
`public static function connect(string $path): void`

@throws Exception

### getDb()
`public static function getDb(): PDO`

### getSetting()
`public static function getSetting(string $key, ?mixed $default = null): ?mixed`

Fetches a single value from the settings table

@param string $key
@param mixed $default
@return mixed

### getAllSettings()
`public static function getAllSettings(): array`

Gets all settings as an associative array

### getTranslations()
`public static function getTranslations(): array`

### getUrlTranslations()
`public static function getUrlTranslations(): array`

### getKinds()
`public static function getKinds(): array`
