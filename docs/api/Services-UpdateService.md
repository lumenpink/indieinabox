# UpdateService
**Namespace:** `Indieinabox\Services`

Static facade for UpdateServiceInterface operations.

## Properties

### `public static ?string $customExecutablePath`

Optional override for the executable path (used for testing and custom runners).

### `public static ?string $customVersionsDir`

Optional override for versions directory (used for testing).

## Methods

### getManager()
`private static function getManager(): Indieinabox\Services\Contracts\UpdateServiceInterface`

### getVersionsDir()
`public static function getVersionsDir(): string`

### checkAvailableVersions()
`public static function checkAvailableVersions(): array`

### getAvailableUpdates()
`public static function getAvailableUpdates(): array`

### getLatestRelease()
`public static function getLatestRelease(bool $includePrerelease = false): ?array`

### downloadAndInstall()
`public static function downloadAndInstall(string $downloadUrl): bool`

### backupCurrentVersion()
`public static function backupCurrentVersion(): string|false`

### rollback()
`public static function rollback(?string $backupFilename = null): bool`

### getLocalBackups()
`public static function getLocalBackups(): array`

### cleanupOldBackups()
`public static function cleanupOldBackups(): int`

### processScheduledUpdate()
`public static function processScheduledUpdate(int $intervalSeconds = 21600): array`

### getCurrentExecutablePath()
`public static function getCurrentExecutablePath(): string`
