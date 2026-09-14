# OutgoingWebmentionDispatcher
**Namespace:** `Indieinabox\BackgroundWorker`

Class OutgoingWebmentionDispatcher

Processes pending outgoing Webmentions queued in outgoing_webmentions table,
discovers target endpoints, and dispatches POST requests.

## Properties

### `private Indieinabox\Site $site`

### `private PDO $db`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, PDO $db)`

### process()
`public function process(): void`

Processes pending outgoing webmentions.

@return void
