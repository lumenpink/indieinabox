# ArchiveService
**Namespace:** `Indieinabox\Services`

Service managing archived link snapshots, URL alias resolutions, and forced snapshot queueing.

## Properties

### `private PDO $db`

## Methods

### __construct()
`public function __construct(?PDO $db = null)`

### resolveAlias()
`public function resolveAlias(string $url): string`

Resolves potential archive aliases to find the canonical target URL.

### findSnapshot()
`public function findSnapshot(string $url, ?int $timestamp = null): ?array`

Finds the closest archived link snapshot by timestamp.

@return array<string, mixed>|null

### queueForceArchive()
`public function queueForceArchive(string $url): bool`

Enqueues a URL for forced archive capture.
