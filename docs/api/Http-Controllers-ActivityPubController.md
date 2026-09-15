# ActivityPubController
**Namespace:** `Indieinabox\Http\Controllers`

Controller managing HTTP endpoints for ActivityPub federation, actor discovery, and inbox/outbox.

## Properties

### `private Indieinabox\ActivityPubHandler $handler`

### `protected Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\ActivityPubHandler $handler = null)`

### interact()
`public function interact(): void`

### authorizeInteraction()
`public function authorizeInteraction(): void`

### webfinger()
`public function webfinger(): void`

### actor()
`public function actor(): void`

### inbox()
`public function inbox(): void`

### outbox()
`public function outbox(): void`

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
