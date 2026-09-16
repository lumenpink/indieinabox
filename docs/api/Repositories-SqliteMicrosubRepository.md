# SqliteMicrosubRepository
**Namespace:** `Indieinabox\Repositories`

SQLite implementation of MicrosubRepositoryInterface.

## Properties

### `private ?PDO $db`

## Methods

### __construct()
`public function __construct(?PDO $db = null)`

### getDb()
`private function getDb(): PDO`

### getChannels()
`public function getChannels(): array`

### createChannel()
`public function createChannel(string $uid, string $name): bool`

### deleteChannel()
`public function deleteChannel(string $uid): bool`

### getSubscriptions()
`public function getSubscriptions(string $channelUid): array`

### addSubscription()
`public function addSubscription(string $channelUid, string $url, string $type = 'feed', string $name = '', string $photo = ''): bool`

### getSubscriptionType()
`public function getSubscriptionType(string $channelUid, string $url): ?string`

### removeSubscription()
`public function removeSubscription(string $channelUid, string $url): bool`

### countSubscriptionsByUrl()
`public function countSubscriptionsByUrl(string $url): int`
