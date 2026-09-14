# Updater
**Namespace:** `Indieinabox`

## Methods

### checkAvailableVersions()
`public static function checkAvailableVersions(): void`

### downloadAndInstall()
`public static function downloadAndInstall(string $downloadUrl): bool`

### backupCurrentVersion()
`public static function backupCurrentVersion(): void`

### rollback()
`public static function rollback(string $backupFilename): bool`

### getLocalBackups()
`public static function getLocalBackups(): array`

### cleanupOldBackups()
`private static function cleanupOldBackups(): void`

### getCurrentExecutablePath()
`private static function getCurrentExecutablePath(): string`

Determines the path of the current main executable (index.php or indieinabox.php).
