# MicrosubService
**Namespace:** `Indieinabox\Services`

Domain service managing Microsub channels, subscriptions, timeline retrieval, and interactions.

## Properties

### `private PDO $db`

### `private Indieinabox\Services\FetchFeedsService $feedFetcher`

### `private ?Indieinabox\Site\Site $site`

### `private Indieinabox\Repositories\Contracts\MicrosubRepositoryInterface $repository`

### `private Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface $activityPubRepo`

## Methods

### __construct()
`public function __construct(?PDO $db = null, ?Indieinabox\Services\FetchFeedsService $feedFetcher = null, ?Indieinabox\Site\Site $site = null, ?Indieinabox\Repositories\Contracts\MicrosubRepositoryInterface $repository = null, ?Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface $activityPubRepo = null)`

### getChannels()
`public function getChannels(): array`

Lists all Microsub channels.

@return array<int, array{uid: string, name: string}>

### createChannel()
`public function createChannel(string $name): array`

Creates a new Microsub channel.

@param string $name Channel display name.
@return array{uid: string, name: string}

### deleteChannel()
`public function deleteChannel(string $uid): bool`

Deletes a custom channel and its subscriptions.
Default channels ('inbox', 'notifications') cannot be deleted.

### getTimeline()
`public function getTimeline(string $channel = 'inbox', int $before = 0, int $after = 0, int $limit = 20): array`

Retrieves the timeline items for a channel.

@return array{items: array<int, mixed>, paging?: array{before: ?int, after: ?int}}

### markRead()
`public function markRead(string $channel, array $entryIds): bool`

Marks one or more entries as read.

@param string $channel Channel ID.
@param array<int, string> $entryIds List of entry UIDs.

### getSubscriptions()
`public function getSubscriptions(string $channel = 'inbox'): array`

Lists all feed subscriptions for a given channel.

@return array<int, array{type: string, url: string, feed_type: string, name: string, photo: string}>

### follow()
`public function follow(string $channel, string $url): array`

Subscribes to a feed or ActivityPub actor.

@return array{type: string, url: string, feed_type: string, name: string}

### unfollow()
`public function unfollow(string $channel, string $url): bool`

Unsubscribes from a feed and removes its cached items.

### search()
`public function search(string $query): array`

Discovers feeds linked in a target web page.

@return array<int, array{type: string, url: string}>

### interact()
`public function interact(string $targetUrl, string $actionType, string $content = ''): array`

Dispatches an interaction (like, repost, reply, poll vote) to ActivityPub.

@return array{success: string, activity_id: string}

### syncFeeds()
`public function syncFeeds(): int`

Triggers sync of all feeds.

### fetchUrl()
`protected function fetchUrl(string $url, mixed $context = null)`

@param null|resource $context

@return false|string
