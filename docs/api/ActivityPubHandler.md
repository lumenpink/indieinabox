# ActivityPubHandler
**Namespace:** `Indieinabox`

Class ActivityPubHandler

## Properties

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

### `private PDO $db`

@var PDO

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Method __construct
@param \Indieinabox\Site $site

### ensureKeys()
`private function ensureKeys(): void`

Method ensureKeys
@return void

### handleWebFinger()
`public function handleWebFinger(): void`

Method handleWebFinger
@return void

### handleActor()
`public function handleActor(): void`

Method handleActor
@return void

### handleInbox()
`public function handleInbox(): void`

Method handleInbox
@return void

### queueAcceptFollow()
`private function queueAcceptFollow(array $followActivity, string $targetInbox): void`

Method queueAcceptFollow
@param array $followActivity
@param string $targetInbox

@return void

### handleOutbox()
`public function handleOutbox(): void`

Method handleOutbox
@return void

### queueCreateActivity()
`public function queueCreateActivity(string $postUrl, string $content, ?string $name): void`

Method queueCreateActivity
@param string $postUrl
@param string $content
@param ?string $name

@return void
