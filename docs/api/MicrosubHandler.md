# MicrosubHandler
**Namespace:** `Indieinabox`

Class MicrosubHandler

## Properties

### `private Indieinabox\IndieAuthHandler $authHandler`

@var \Indieinabox\IndieAuthHandler

### `private PDO $db`

@var PDO

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the MicrosubHandler.

@param \Indieinabox\Site $site Global site configuration and environment.

### handle()
`public function handle(): void`

Main entry point for handling Microsub requests.
Enforces authentication and routes to handleGet or handlePost.

@return void

### handleGet()
`private function handleGet(string $action): void`

Handles Microsub GET actions (channels, timeline, search).
Retrieves lists of subscribed feeds or items in a feed.

@param string $action The requested action ('channels', 'timeline', 'search', etc).
@return void

### handlePost()
`private function handlePost(string $action): void`

Handles Microsub POST actions (subscribe, unsubscribe, mute, block, mark read).
Modifies subscriptions or state in the underlying JSON data files.

@param string $action The requested action.
@return void

### fetchUrl()
`protected function fetchUrl(string $url, mixed $context = null)`

Helper to fetch remote URL contents. Overridable in tests to avoid real network access.

@param string $url
@param resource|null $context
@return string|false
