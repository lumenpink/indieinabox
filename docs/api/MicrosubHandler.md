# MicrosubHandler
**Namespace:** `Indieinabox`

HTTP handler for Microsub server endpoints (channels, timeline, actions).

## Properties

### `private Indieinabox\IndieAuthHandler $authHandler`

### `private Indieinabox\Services\MicrosubService $service`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\IndieAuthHandler $authHandler = null, ?Indieinabox\Services\MicrosubService $service = null)`

### handle()
`public function handle(): void`

Main entry point for handling Microsub requests.

### handleGet()
`private function handleGet(string $action): void`

Handles Microsub GET actions (channels, timeline, search, follow).

### handlePost()
`private function handlePost(string $action): void`

Handles Microsub POST actions (channels, timeline, interact, follow, unfollow, fetch).

### getRemoteUrl()
`public function getRemoteUrl(string $url, mixed $context = null)`

Internal proxy to fetchUrl so anonymous service adapter can invoke it.

### fetchUrl()
`protected function fetchUrl(string $url, mixed $context = null)`

Helper to fetch remote URL contents. Overridable in tests to avoid real network access.

@param string $url
@param resource|null $context
@return string|false
