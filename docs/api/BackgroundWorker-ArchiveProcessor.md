# ArchiveProcessor
**Namespace:** `Indieinabox\BackgroundWorker`

Class ArchiveProcessor

Processes pending items in archive_queue, sending target links to the Wayback Machine
and generating local PDF snapshots via the Microlink API.

## Properties

### `private Indieinabox\Site $site`

### `private PDO $db`

### `private array $callbacks`

@var array<string, callable>

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, PDO $db, array $callbacks = [])`

@param Site $site
@param PDO $db
@param array<string, callable> $callbacks

### process()
`public function process(): void`

Processes the archive queue.

@return void

### resolveFinalUrl()
`public function resolveFinalUrl(string $url): string`

Resolves final destination URL following HTTP redirects.

@param string $url
@return string

### sendToArchiveOrg()
`public function sendToArchiveOrg(string $url): void`

Submits a URL to the Wayback Machine.

@param string $url
@return void

### fetchPdfFromMicrolink()
`public function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string`

Generates and downloads a PDF snapshot of a URL via Microlink API.

@param string $url
@param string $normUrl
@param string $pdfDir
@return string|null

### fetchJsonUrl()
`protected function fetchJsonUrl(string $url): ?array`

Fetches JSON array from URL.

@param string $url
@return array<string, mixed>|null

### fetchUrl()
`protected function fetchUrl(string $url): string|false`

Fetches raw URL content.

@param string $url
@return string|false
