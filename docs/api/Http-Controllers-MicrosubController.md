# MicrosubController
**Namespace:** `Indieinabox\Http\Controllers`

Controller handling Microsub server endpoints (channels, timeline, actions) and the reader UI.

## Properties

### `private Indieinabox\MicrosubHandler $serverHandler`

### `private Indieinabox\MicrosubReaderHandler $readerHandler`

### `protected Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\MicrosubHandler $serverHandler = null, ?Indieinabox\MicrosubReaderHandler $readerHandler = null)`

### handle()
`public function handle(): void`

Handles standard Microsub API endpoint requests.

### reader()
`public function reader(): void`

Handles the Microsub web reader interface.

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
