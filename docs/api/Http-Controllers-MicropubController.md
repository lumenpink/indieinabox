# MicropubController
**Namespace:** `Indieinabox\Http\Controllers`

Controller handling Micropub server queries, post creation, media uploads, and the web-based posting client.

## Properties

### `private Indieinabox\MicropubHandler $serverHandler`

### `private Indieinabox\MicropubClientHandler $clientHandler`

### `protected Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\MicropubHandler $serverHandler = null, ?Indieinabox\MicropubClientHandler $clientHandler = null)`

### handle()
`public function handle(): void`

Handles standard Micropub endpoint requests (POST create/media, GET config/syndicate-to).

### client()
`public function client(): void`

Handles the Micropub local web admin posting client.

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
