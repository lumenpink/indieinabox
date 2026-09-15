# SqliteSettingsRepository
**Namespace:** `Indieinabox\Repositories`

SQLite-backed implementation of SettingsRepositoryInterface.

## Properties

### `private ?PDO $db`

## Methods

### __construct()
`public function __construct(?PDO $db = null)`

### getDb()
`private function getDb(): PDO`

### get()
`public function get(string $key, ?mixed $default = null): ?mixed`

### set()
`public function set(string $key, ?mixed $value): bool`

### all()
`public function all(): array`

### getTranslations()
`public function getTranslations(): array`

### getUrlTranslations()
`public function getUrlTranslations(): array`

### getKinds()
`public function getKinds(): array`

### saveKinds()
`public function saveKinds(array $kinds): bool`

### saveTranslations()
`public function saveTranslations(array $translations): bool`

### saveUrlTranslations()
`public function saveUrlTranslations(array $urlTranslations): bool`
