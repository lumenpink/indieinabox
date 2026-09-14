# Updater
**Namespace:** `Indieinabox`

## Properties

### `public static ?string $customExecutablePath`

Optional override for the executable path (used for testing and custom runners).

### `public static ?string $customVersionsDir`

Optional override for versions directory (used for testing).

## Methods

### getVersionsDir()
`public static function getVersionsDir(): string`

Returns the directory where version backups are stored.

@return string

### checkAvailableVersions()
`public static function checkAvailableVersions(): array`

Checks Codeberg API for available releases and caches them in the database.

@return array<int, array<string, mixed>>

### getAvailableUpdates()
`public static function getAvailableUpdates(): array`

Retrieves currently cached available updates.

@return array<int, array<string, mixed>>

### getLatestRelease()
`public static function getLatestRelease(bool $includePrerelease = false): ?array`

Finds the latest release matching release preference.

@param bool $includePrerelease
@return array<string, mixed>|null

### downloadAndInstall()
`public static function downloadAndInstall(string $downloadUrl): bool`

Downloads and installs an update from the specified URL.
Performs a backup before overwriting.

@param string $downloadUrl
@return bool

### backupCurrentVersion()
`public static function backupCurrentVersion(): string|false`

Backs up the current executable, retaining only the MAX_BACKUPS most recent backups.

@return string|false Path to the created backup file, or false if backup failed.

### rollback()
`public static function rollback(?string $backupFilename = null): bool`

Rolls back to a previous backup.

@param string|null $backupFilename Filename of backup to restore. If null, restores the latest backup.
@return bool

### getLocalBackups()
`public static function getLocalBackups(): array`

Retrieves the list of local backup files, sorted with newest first.

@return array<int, array{filename: string, path: string, version: ?string, date: int, formatted_date: string, size: int}>

### cleanupOldBackups()
`public static function cleanupOldBackups(): int`

Enforces the MAX_BACKUPS limit by deleting the oldest backups.

@return int Number of deleted backup files.

### processScheduledUpdate()
`public static function processScheduledUpdate(int $intervalSeconds = 21600): array`

Executes scheduled update checking and auto-upgrade logic for CRON.

@param int $intervalSeconds Interval in seconds between checks (default: 21600 = 6 hours).
@return array{checked: bool, upgraded: bool, message: string}

### getCurrentExecutablePath()
`public static function getCurrentExecutablePath(): string`

Determines the path of the current main executable (indieinabox.php, index.php, or build.php).

@return string
