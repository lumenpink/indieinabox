# UpdateManager
**Namespace:** `Indieinabox\Services`

Service managing application updates, release downloads, and binary backups.

## Properties

### `private Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settingsRepo`

## Methods

### __construct()
`public function __construct(?Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settingsRepo = null)`

### getVersionsDir()
`public function getVersionsDir(): string`

### checkAvailableVersions()
`public function checkAvailableVersions(): array`

### getAvailableUpdates()
`public function getAvailableUpdates(): array`

### getLatestRelease()
`public function getLatestRelease(bool $includePrerelease = false): ?array`

### downloadAndInstall()
`public function downloadAndInstall(string $downloadUrl): bool`

### backupCurrentVersion()
`public function backupCurrentVersion(): string|false`

### rollback()
`public function rollback(?string $backupFilename = null): bool`

### getLocalBackups()
`public function getLocalBackups(): array`

### cleanupOldBackups()
`public function cleanupOldBackups(): int`

### processScheduledUpdate()
`public function processScheduledUpdate(int $intervalSeconds = 21600): array`

### getCurrentExecutablePath()
`public function getCurrentExecutablePath(): string`
