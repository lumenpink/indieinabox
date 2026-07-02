# ActivityPubHandler
**Namespace:** `Indieinabox`

## Properties

### `private Indieinabox\Site $site`

### `private PDO $db`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### ensureKeys()
`private function ensureKeys(): void`

### handleWebFinger()
`public function handleWebFinger(): void`

### handleActor()
`public function handleActor(): void`

### handleInbox()
`public function handleInbox(): void`

### queueAcceptFollow()
`private function queueAcceptFollow(array $followActivity, string $targetInbox): void`

### handleOutbox()
`public function handleOutbox(): void`

### queueCreateActivity()
`public function queueCreateActivity(string $postUrl, string $content, ?string $name): void`
