# BackupService
**Namespace:** `Indieinabox\Services`

Service handling site backups and file rotation.

## Properties

### `private Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(?Indieinabox\Site\Site $site = null)`

### run()
`public function run(bool $skipContent = false, bool $skipMedia = false): void`

### rotateBackups()
`public function rotateBackups(string $destDir, int $limit = 5): void`
