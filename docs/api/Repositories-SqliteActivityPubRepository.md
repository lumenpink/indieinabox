# SqliteActivityPubRepository
**Namespace:** `Indieinabox\Repositories`

SQLite implementation of ActivityPubRepositoryInterface.

## Properties

### `private ?PDO $db`

## Methods

### __construct()
`public function __construct(?PDO $db = null)`

### getDb()
`private function getDb(): PDO`

### addFollower()
`public function addFollower(string $actorUrl, string $inboxUrl, ?string $sharedInboxUrl = null): bool`

### removeFollower()
`public function removeFollower(string $actorUrl): bool`

### isFollower()
`public function isFollower(string $actorUrl): bool`

### getFollowers()
`public function getFollowers(): array`

### getDistinctInboxes()
`public function getDistinctInboxes(): array`

### enqueueOutbox()
`public function enqueueOutbox(string $payloadJson, string $targetInbox, ?int $createdAt = null): int`

### getPendingOutbox()
`public function getPendingOutbox(int $limit = 50): array`

### updateOutboxStatus()
`public function updateOutboxStatus(int $id, string $status): bool`

### pruneOutbox()
`public function pruneOutbox(int $olderThanTimestamp): int`
