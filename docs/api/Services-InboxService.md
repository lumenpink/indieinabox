# InboxService
**Namespace:** `Indieinabox\Services`

Service orchestrating incoming federated activities, follow requests, and interaction ingestion.

## Properties

### `private Indieinabox\Site $site`

### `private Indieinabox\Federation\FederationManager $federationManager`

### `private Indieinabox\Services\FollowService $followService`

### `private Indieinabox\Services\OutboxService $outboxService`

### `private PDO $db`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, Indieinabox\Federation\FederationManager $federationManager, Indieinabox\Services\FollowService $followService, Indieinabox\Services\OutboxService $outboxService, ?PDO $db = null)`

### enqueue()
`public function enqueue(string $type, array|string $payload): int`

Enqueues an incoming payload for asynchronous processing.

@param string $type
@param array<string, mixed>|string $payload
@return int Inserted ID

### handleActivity()
`public function handleActivity(array $activity): bool`

Synchronously processes a normalized incoming federated activity.

@param array<string, mixed> $activity
@return bool

### handleFollow()
`private function handleFollow(array $activity, string $actorUrl): bool`

Handles a Follow activity by recording the follower and sending an Accept activity.

@param array<string, mixed> $activity
@param string $actorUrl
@return bool

### processPendingQueue()
`public function processPendingQueue(int $limit = 50): int`

Drains and processes queued items from inbox_queue.

@param int $limit Maximum number of queued items to process.
@return int Number of processed items.
