# FollowService
**Namespace:** `Indieinabox\Services`

Service managing federated follower relationships and inbox distribution targets.

## Properties

### `private Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface $repository`

## Methods

### __construct()
`public function __construct(Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface|PDO|null $repository = null)`

### addFollower()
`public function addFollower(string $actorUrl, string $inboxUrl, ?string $sharedInboxUrl = null): bool`

Records or updates an active remote follower.

### removeFollower()
`public function removeFollower(string $actorUrl): bool`

Removes a follower upon receiving an Unfollow activity.

### isFollower()
`public function isFollower(string $actorUrl): bool`

Checks if the given remote actor is a current follower.

### getFollowers()
`public function getFollowers(): array`

Retrieves all follower records.

@return array<int, array{actor_url: string, inbox_url: string, shared_inbox_url: ?string}>

### getDistinctInboxes()
`public function getDistinctInboxes(): array`

Returns deduplicated inbox endpoints (prioritizing sharedInboxes) for fan-out broadcasting.

@return array<int, string>
