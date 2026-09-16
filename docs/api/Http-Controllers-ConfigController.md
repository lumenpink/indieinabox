# ConfigController
**Namespace:** `Indieinabox\Http\Controllers`

Controller specifically managing site and engine configuration settings in the admin dashboard.

## Properties

### `private Indieinabox\Http\Controllers\AdminController $adminController`

### `private Indieinabox\Services\ConfigurationService $configService`

### `protected Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\Http\Controllers\AdminController $adminController = null, ?Indieinabox\Services\ConfigurationService $configService = null)`

### getConfigurationService()
`public function getConfigurationService(): Indieinabox\Services\ConfigurationService`

### handle()
`public function handle(): void`

Dispatches the config handling logic.

### getSite()
`public function getSite(): Indieinabox\Site`

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
