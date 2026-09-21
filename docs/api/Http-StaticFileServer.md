# StaticFileServer
**Namespace:** `Indieinabox\Http`

Serves static assets, compiled HTML, and media files from site output directories,
with content negotiation for ActivityPub representations.

## Properties

### `private Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site)`

### serve()
`public function serve(?string $requestUri = null): void`

Attempts to serve static files from the output directory based on the request URI.

### renderNotFound()
`protected function renderNotFound(string $filePath, string $base, string $outputDir): void`

Renders a customized, stylish 404 Not Found response.

### getMimeType()
`public function getMimeType(string $extension): string`

Resolves the MIME content-type for a file extension.
