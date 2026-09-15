# AdminController
**Namespace:** `Indieinabox\Http\Controllers`

Controller managing administrative panels (settings, config, client, reader, moderation, cron).

## Properties

### `private Indieinabox\ConfigHandler $configHandler`

### `private Indieinabox\MicropubClientHandler $micropubClientHandler`

### `private Indieinabox\MicrosubReaderHandler $microsubReaderHandler`

### `private Indieinabox\ModerationHandler $moderationHandler`

### `protected Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\ConfigHandler $configHandler = null, ?Indieinabox\MicropubClientHandler $micropubClientHandler = null, ?Indieinabox\MicrosubReaderHandler $microsubReaderHandler = null, ?Indieinabox\ModerationHandler $moderationHandler = null)`

### index()
`public function index(): void`

### config()
`public function config(): void`

### micropub()
`public function micropub(): void`

### microsub()
`public function microsub(): void`

### moderation()
`public function moderation(): void`

### cron()
`public function cron(): void`

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
