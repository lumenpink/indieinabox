# AdminController
**Namespace:** `Indieinabox\Http\Controllers`

Controller managing administrative panels (settings, config, client, reader, moderation, cron).

## Properties

### `private Indieinabox\Services\ConfigurationService $configService`

### `private Indieinabox\Services\ModerationService $moderationService`

### `private ?Indieinabox\Services\MicrosubService $microsubService`

### `private ?PDO $db`

### `private ?Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settingsRepo`

### `protected Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, ?Indieinabox\Services\ConfigurationService $configService = null, ?Indieinabox\Services\ModerationService $moderationService = null, ?Indieinabox\Services\MicrosubService $microsubService = null, ?PDO $db = null, ?Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settingsRepo = null)`

### getConfigurationService()
`public function getConfigurationService(): Indieinabox\Services\ConfigurationService`

### getModerationService()
`public function getModerationService(): Indieinabox\Services\ModerationService`

### getMicrosubService()
`public function getMicrosubService(): Indieinabox\Services\MicrosubService`

### index()
`public function index(): void`

### config()
`public function config(): void`

Admin site configuration endpoint.

### micropub()
`public function micropub(): void`

Admin Micropub web posting client.

### microsub()
`public function microsub(): void`

Admin Microsub timeline reader.

### moderation()
`public function moderation(): void`

Admin interactions and comments moderation panel.

### cron()
`public function cron(): void`

Cron endpoint triggering background processing.

### handleBootstrap()
`private function handleBootstrap(): void`

### redirectToAuth()
`private function redirectToAuth(): void`

### handleCallback()
`private function handleCallback(): void`

### saveConfig()
`protected function saveConfig(): void`

### rebuildSite()
`public function rebuildSite(): void`

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
