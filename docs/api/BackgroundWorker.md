# BackgroundWorker
**Namespace:** `Indieinabox`

Class BackgroundWorker

Coordinates and dispatches background tasks: inbox queue, outbox delivery,
outgoing webmentions, archive snapshotting, backups, and updates.

## Properties

### `private PDO $db`

@var PDO

### `private Indieinabox\Site $site`

@var Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the BackgroundWorker.

@param Site $site Global site configuration and environment.

### runAll()
`public function runAll(): void`

Executes all background tasks (inbox, outbox, archives, feeds, updates).
Normally called periodically via cron or CLI.

@return void

### processWebmentionDiscovery()
`public function processWebmentionDiscovery(): void`

Discovers Webmention support for queued domains.

@return void

### processBackups()
`public function processBackups(): void`

Executes the daily backup if cron is enabled.

@return void

### processUpdates()
`public function processUpdates(): void`

Checks for application updates and performs auto-upgrade if enabled.

@return void

### processTwtxtFeeds()
`public function processTwtxtFeeds(): void`

Fetches remote Twtxt timeline and hub mentions asynchronously.
Rebuilds the site to update the static timeline page if new entries are found.

@return void

### processInboxQueue()
`public function processInboxQueue(): void`

Processes the incoming queue (Webmentions, ActivityPub activities, site rebuilds).

@return void

### processOutgoingWebmentions()
`public function processOutgoingWebmentions(): void`

Processes outgoing webmentions.

@return void

### processOutbox()
`public function processOutbox(): void`

Processes the outgoing queue (Outbox).
Delivers queued activities to followers' inboxes using HTTP Signatures.

@return void

### processArchiveQueue()
`public function processArchiveQueue(): void`

Processes the archive queue.
Saves external links to Archive.org and downloads local PDF snapshots via Microlink.

@return void

### getInboxCallbacks()
`protected function getInboxCallbacks(): array`

@return array<string, callable>

### getArchiveCallbacks()
`protected function getArchiveCallbacks(): array`

@return array<string, callable>

### verifySignature()
`protected function verifySignature(array $headers, string $method, string $path, string $pubKey): bool`

Verifies HTTP signature.

@param array $headers
@param string $method
@param string $path
@param string $pubKey
@return bool

### fetchJsonUrl()
`protected function fetchJsonUrl(string $url): ?array`

Fetches a URL and decodes the JSON response.

@param string $url The URL to fetch.
@return array|null The decoded JSON array, or null on failure.

### fetchUrl()
`protected function fetchUrl(string $url)`

Fetches remote content over HTTP.

@param string $url
@return string|false

### resolveFinalUrl()
`protected function resolveFinalUrl(string $url): string`

Follows redirects to determine the canonical destination URL.

@param string $url
@return string

### sendToArchiveOrg()
`protected function sendToArchiveOrg(string $url): void`

Submits a URL to the Wayback Machine save endpoint.

@param string $url
@return void

### fetchPdfFromMicrolink()
`protected function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string`

Fetches a PDF snapshot from the Microlink API.

@param string $url
@param string $normUrl
@param string $pdfDir
@return string|null
