# OutboxDispatcher
**Namespace:** `Indieinabox\BackgroundWorker`

Class OutboxDispatcher

Processes pending ActivityPub messages in activitypub_outbox,
cryptographically signs HTTP POST requests using the instance RSA key,
and delivers them to remote Fediverse inboxes.

## Properties

### `private Indieinabox\Site\Site $site`

### `private PDO $db`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, PDO $db)`

### process()
`public function process(): void`

Processes the outbox queue.

@return void
