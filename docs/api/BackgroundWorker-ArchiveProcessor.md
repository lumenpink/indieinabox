# ArchiveProcessor
**Namespace:** `Indieinabox\BackgroundWorker`

Class ArchiveProcessor

Processes pending items in archive_queue, sending target links to the Wayback Machine
and generating local PDF snapshots via the Microlink API.

## Properties

### `private Indieinabox\Site\Site $site`

### `private PDO $db`

### `private mixed $urlResolver`

@var callable|null

### `private mixed $archiveOrgSender`

@var callable|null

### `private mixed $pdfFetcher`

@var callable|null

### `private mixed $fetcher`

@var callable|null

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, PDO $db, ?callable $urlResolver = null, ?callable $archiveOrgSender = null, ?callable $pdfFetcher = null, ?callable $fetcher = null)`

@param Site $site
@param PDO $db
@param callable|null $urlResolver Optional hook fn(string $url): string
@param callable|null $archiveOrgSender Optional hook fn(string $url): void
@param callable|null $pdfFetcher Optional hook fn(string $url, string $normUrl, string $pdfDir): ?string
@param callable|null $fetcher Optional hook fn(string $url): string|false

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

Submits a URL to the Wayback Machine save endpoint.

@param string $url
@return void

### fetchPdfFromMicrolink()
`public function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string`

Fetches a PDF snapshot from the Microlink API.

@param string $url
@param string $normUrl
@param string $pdfDir
@return string|null

### fetchUrl()
`public function fetchUrl(string $url)`

Fetches remote content over HTTP.

@param string $url
@return string|false
