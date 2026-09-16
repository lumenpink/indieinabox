# AbstractController
**Namespace:** `Indieinabox\Http\Controllers`

Base HTTP Controller providing standard request handling and response emission methods.

## Properties

### `protected Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site)`

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
