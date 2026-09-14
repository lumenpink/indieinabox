# OutboxService
**Namespace:** `Indieinabox\Services`

Service managing outgoing federation queues, fan-out broadcasting, and delivery execution.

## Properties

### `private Indieinabox\Federation\FederationManager $federationManager`

### `private Indieinabox\Services\FollowService $followService`

### `private PDO $db`

## Methods

### __construct()
`public function __construct(Indieinabox\Federation\FederationManager $federationManager, Indieinabox\Services\FollowService $followService, ?PDO $db = null)`

### enqueueDelivery()
`public function enqueueDelivery(array|string $payload, string $targetInbox): int`

Enqueues an activity delivery targeting a specific inbox endpoint.

@param array<string, mixed>|string $payload
@param string $targetInbox
@return int Inserted message ID

### broadcastActivity()
`public function broadcastActivity(array|string $payload): int`

Broadcasts an activity to all distinct registered follower inboxes.

@param array<string, mixed>|string $payload
@return int Number of inboxes queued

### dispatchPending()
`public function dispatchPending(int $limit = 50, string $protocol = 'activitypub'): int`

Dispatches pending outbox queue items using the appropriate federation adapter.

@param int $limit Maximum number of pending records to process.
@param string $protocol Protocol adapter to use for delivery.
@return int Number of successfully delivered messages.
