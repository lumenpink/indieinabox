# FileInteractionRepository
**Namespace:** `Indieinabox\Repositories`

File-backed repository storing interactions in channel markdown files.

## Properties

### `private string $dataDir`

### `private Indieinabox\Yaml $yaml`

## Methods

### __construct()
`public function __construct(?string $dataDir = null, ?Indieinabox\Yaml $yaml = null)`

### getNotificationsDir()
`private function getNotificationsDir(): string`

### getSpamDir()
`private function getSpamDir(): string`

### findByPageSlug()
`public function findByPageSlug(string $slug, ?string $type = null): array`

### listByStatus()
`public function listByStatus(string $status = 'pending'): array`

### updateStatus()
`public function updateStatus(string $id, string $status, string $type = 'pending'): bool`

### save()
`public function save(string $id, array $metadata, string $content = '', string $channel = 'notifications'): bool`

### delete()
`public function delete(string $id, string $type = 'pending'): bool`
