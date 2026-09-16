# ActivityPubRepositoryInterface
**Namespace:** `Indieinabox\Repositories\Contracts`

Interface ActivityPubRepositoryInterface

Defines persistence operations for ActivityPub followers and outgoing delivery queues.

## Methods

### addFollower()
`abstract public function addFollower(string $actorUrl, string $inboxUrl, ?string $sharedInboxUrl = null): bool`

Records or updates a remote actor follower.

### removeFollower()
`abstract public function removeFollower(string $actorUrl): bool`

Removes an active follower record.

### isFollower()
`abstract public function isFollower(string $actorUrl): bool`

Checks if a remote actor is a registered follower.

### getFollowers()
`abstract public function getFollowers(): array`

Retrieves all follower records.

@return array<int, array{actor_url: string, inbox_url: string, shared_inbox_url: ?string}>

### getDistinctInboxes()
`abstract public function getDistinctInboxes(): array`

Returns deduplicated target inbox endpoints (prioritizing shared inboxes).

@return array<int, string>

### enqueueOutbox()
`abstract public function enqueueOutbox(string $payloadJson, string $targetInbox, ?int $createdAt = null): int`

Enqueues a payload for delivery to a target inbox.

@return int Inserted record ID

### getPendingOutbox()
`abstract public function getPendingOutbox(int $limit = 50): array`

Retrieves pending outbox items up to the specified limit.

@return array<int, array{id: int, payload_json: string, target_inbox: string}>

### updateOutboxStatus()
`abstract public function updateOutboxStatus(int $id, string $status): bool`

Updates delivery status for an outbox record ('sent', 'failed', etc.).

### pruneOutbox()
`abstract public function pruneOutbox(int $olderThanTimestamp): int`

Prunes processed outbox records older than a given timestamp.

@return int Number of rows pruned
