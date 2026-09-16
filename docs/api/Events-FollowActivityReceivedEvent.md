# FollowActivityReceivedEvent
**Namespace:** `Indieinabox\Events`

Dispatched when a remote ActivityPub follower activity (Follow or Undo Follow) is processed.

## Properties

### `private string $actorUrl`

### `private string $inboxUrl`

### `private string $activityType`

### `private ?string $sharedInboxUrl`

## Methods

### __construct()
`public function __construct(string $actorUrl, string $inboxUrl, string $activityType = 'Follow', ?string $sharedInboxUrl = null)`

### getActorUrl()
`public function getActorUrl(): string`

### getInboxUrl()
`public function getInboxUrl(): string`

### getActivityType()
`public function getActivityType(): string`

### getSharedInboxUrl()
`public function getSharedInboxUrl(): ?string`

### occurredOn()
`public function occurredOn(): DateTimeImmutable`

### eventId()
`public function eventId(): string`

### isPropagationStopped()
`public function isPropagationStopped(): bool`

### stopPropagation()
`public function stopPropagation(): void`
