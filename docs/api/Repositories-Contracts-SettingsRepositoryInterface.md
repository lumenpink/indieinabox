# SettingsRepositoryInterface
**Namespace:** `Indieinabox\Repositories\Contracts`

Contract for application settings, kind definitions, and translations storage.

## Methods

### get()
`abstract public function get(string $key, ?mixed $default = null): ?mixed`

Retrieves a single setting by key, with optional default fallback.

### set()
`abstract public function set(string $key, ?mixed $value): bool`

Persists or updates a single setting.

### all()
`abstract public function all(): array`

Retrieves all settings as an associative key-value map.

@return array<string, mixed>

### getTranslations()
`abstract public function getTranslations(): array`

Retrieves interface translations grouped by [phrase_key][lang] = phrase_value.

@return array<string, array<string, string>>

### getUrlTranslations()
`abstract public function getUrlTranslations(): array`

Retrieves localized slug translations grouped by [slug_key][lang] = slug_value.

@return array<string, array<string, string>>

### getKinds()
`abstract public function getKinds(): array`

Retrieves content kinds definitions keyed by kind_key.

@return array<string, array<string, mixed>>

### saveKinds()
`abstract public function saveKinds(array $kinds): bool`

Saves or replaces kind configurations.

@param array<string, array<string, mixed>> $kinds

### saveTranslations()
`abstract public function saveTranslations(array $translations): bool`

Saves or updates phrase translations.

@param array<string, array<string, string>> $translations

### saveUrlTranslations()
`abstract public function saveUrlTranslations(array $urlTranslations): bool`

Saves or updates localized slug translations.

@param array<string, array<string, string>> $urlTranslations
