# BackupServiceInterface
**Namespace:** `Indieinabox\Services\Contracts`

Interface BackupServiceInterface

Defines automated archive creation and rotation for site data, content, and configuration.

## Methods

### run()
`abstract public function run(bool $skipContent = false, bool $skipMedia = false): void`

Executes backup archive generation.

### rotateBackups()
`abstract public function rotateBackups(string $destDir, int $limit = 5): void`

Rotates existing backups keeping only the most recent $limit files.
