# Database
**Namespace:** `Indieinabox`

Class Database

## Properties

### `private static ?PDO $db`

### `public static ?string $dataDir`

## Methods

### connect()
`public static function connect(string $path): void`

@throws Exception

### getDb()
`public static function getDb(): PDO`

Method getDb
@return PDO

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

Method getTranslations
@return array

### getUrlTranslations()
`public static function getUrlTranslations(): array`

Method getUrlTranslations
@return array

### getKinds()
`public static function getKinds(): array`

Method getKinds
@return array
