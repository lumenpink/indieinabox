# MicrosubRepositoryInterface
**Namespace:** `Indieinabox\Repositories\Contracts`

Interface MicrosubRepositoryInterface

Defines persistence operations for Microsub channels and subscriptions.

## Methods

### getChannels()
`abstract public function getChannels(): array`

Retrieves all channels.

@return array<int, array{uid: string, name: string}>

### createChannel()
`abstract public function createChannel(string $uid, string $name): bool`

Creates a new channel.

### deleteChannel()
`abstract public function deleteChannel(string $uid): bool`

Deletes a channel and all associated subscriptions.

### getSubscriptions()
`abstract public function getSubscriptions(string $channelUid): array`

Retrieves all subscriptions for a channel.

@return array<int, array{url: string, type: string, name: string, photo: string}>

### addSubscription()
`abstract public function addSubscription(string $channelUid, string $url, string $type = 'feed', string $name = '', string $photo = ''): bool`

Adds a subscription to a channel.

### getSubscriptionType()
`abstract public function getSubscriptionType(string $channelUid, string $url): ?string`

Gets the subscription type (e.g. 'feed' or 'actor') for a channel and url.

### removeSubscription()
`abstract public function removeSubscription(string $channelUid, string $url): bool`

Removes a subscription from a channel.

### countSubscriptionsByUrl()
`abstract public function countSubscriptionsByUrl(string $url): int`

Counts how many channels are subscribed to a given URL.
