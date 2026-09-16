# UpdateServiceInterface
**Namespace:** `Indieinabox\Services\Contracts`

Interface UpdateServiceInterface

Defines version checking, binary download, backup, and rollback capabilities.

## Methods

### getVersionsDir()
`abstract public function getVersionsDir(): string`

Returns the directory where version backups are stored.

### checkAvailableVersions()
`abstract public function checkAvailableVersions(): array`

Checks remote repository for available releases.

@return array<int, array<string, mixed>>

### getAvailableUpdates()
`abstract public function getAvailableUpdates(): array`

Retrieves currently cached available updates.

@return array<int, array<string, mixed>>

### getLatestRelease()
`abstract public function getLatestRelease(bool $includePrerelease = false): ?array`

Finds latest release matching stability preference.

@return array<string, mixed>|null

### downloadAndInstall()
`abstract public function downloadAndInstall(string $downloadUrl): bool`

Downloads and installs update from the specified URL.

### backupCurrentVersion()
`abstract public function backupCurrentVersion(): string|false`

Backs up current executable, enforcing MAX_BACKUPS retention.

@return string|false

### getLocalBackups()
`abstract public function getLocalBackups(): array`

Returns list of local executable backup files.

@return array<int, array{filename: string, path: string, version: ?string, date: int, formatted_date: string, size: int}>

### rollback()
`abstract public function rollback(?string $backupFilename = null): bool`

Rolls back executable to a previous version from backup.

### processScheduledUpdate()
`abstract public function processScheduledUpdate(int $intervalSeconds = 21600): array`

Checks and applies updates based on scheduled interval and auto-upgrade settings.

@return array{checked: bool, upgraded: bool, message: string}

### cleanupOldBackups()
`abstract public function cleanupOldBackups(): int`

Enforces MAX_BACKUPS retention limit.

### getCurrentExecutablePath()
`abstract public function getCurrentExecutablePath(): string`

Determines path to current main executable.
