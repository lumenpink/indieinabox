# MicrosubController
**Namespace:** `Indieinabox\Http\Controllers`

Controller handling Microsub server endpoints (channels, timeline, actions) and the reader UI.

## Properties

### `protected Indieinabox\IndieAuth\TokenManager $tokenManager`

### `protected Indieinabox\Services\MicrosubService $service`

### `protected Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, ?Indieinabox\IndieAuth\TokenManager $tokenManager = null, ?Indieinabox\Services\MicrosubService $service = null)`

### getService()
`public function getService(): Indieinabox\Services\MicrosubService`

### getTokenManager()
`public function getTokenManager(): Indieinabox\IndieAuth\TokenManager`

### handle()
`public function handle(): void`

Handles standard Microsub API endpoint requests.

### handleGet()
`protected function handleGet(string $action): void`

Handles Microsub GET actions (channels, timeline, search, follow).

### handlePost()
`protected function handlePost(string $action): void`

Handles Microsub POST actions (channels, timeline, interact, follow, unfollow, fetch).

### reader()
`public function reader(): void`

Handles the Microsub web reader interface.

### getRemoteUrl()
`public function getRemoteUrl(string $url, mixed $context = null): string|false`

Proxy helper for remote URL fetching.

### fetchUrl()
`protected function fetchUrl(string $url, mixed $context = null): string|false`

Helper to fetch remote URL contents. Overridable in tests to avoid real network access.

### getSite()
`public function getSite(): Indieinabox\Site\Site`

### json()
`protected function json(?mixed $data, int $status = 200, array $headers = []): void`

Emits a JSON response.

@param mixed $data
@param int $status
@param array<string, string> $headers

### html()
`protected function html(string $html, int $status = 200, array $headers = []): void`

Emits an HTML response.

@param string $html
@param int $status
@param array<string, string> $headers

### redirect()
`protected function redirect(string $url, int $status = 302): void`

Emits a redirect header.

### status()
`protected function status(int $status): void`

Sets HTTP status code.

### jsonResponse()
`protected function jsonResponse(?mixed $data, int $status = 200, array $headers = []): void`

@param array<string, string> $headers

### htmlResponse()
`protected function htmlResponse(string $html, int $status = 200, array $headers = []): void`

@param array<string, string> $headers

### redirectResponse()
`protected function redirectResponse(string $url, int $status = 302): void`
