# BackgroundWorker
**Namespace:** `Indieinabox`

Class BackgroundWorker

## Properties

### `private PDO $db`

@var PDO

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the BackgroundWorker.

@param \Indieinabox\Site $site Global site configuration and environment.

### runAll()
`public function runAll(): void`

Executes all background tasks (inbox, outbox, archives).
Normally called periodically via cron or CLI.

@return void

### processWebmentionDiscovery()
`public function processWebmentionDiscovery(): void`

Discovers Webmention support for queued domains.

### processBackups()
`public function processBackups(): void`

Executes the daily backup if cron is enabled.

### processUpdates()
`public function processUpdates(): void`

Checks for application updates and performs auto-upgrade if enabled.

### processTwtxtFeeds()
`public function processTwtxtFeeds(): void`

Fetches remote Twtxt timeline and hub mentions asynchronously.
Rebuilds the site to update the static timeline page if new entries are found.

### processInboxQueue()
`public function processInboxQueue(): void`

Processes the incoming queue (Webmentions, ActivityPub activities).

@return void

### handleBuildSite()
`private function handleBuildSite(): void`

Triggers a site rebuild.
Instantiates the SiteBuilder and recompiles static assets/pages.

@return void

### handleWebmention()
`private function handleWebmention(array $payload): void`

Parses and handles a received Webmention.
Retrieves the source page, extracts microformats, checks for spam, and saves as a comment/like/repost.

@param array $payload The Webmention data (source, target).
@return void

### handleActivityPub()
`private function handleActivityPub(array $payload): void`

Processes a received ActivityPub activity.
Validates the HTTP signature and handles Follow, Undo, Create, or Like actions.

@param array $payload The parsed ActivityPub JSON-LD data.
@return void

### verifySignature()
`protected function verifySignature(array $headers, string $method, string $path, string $pubKey): bool`

Method verifySignature
@param array $headers
@param string $method
@param string $path
@param string $pubKey

@return bool

### saveActivityPubCreate()
`private function saveActivityPubCreate(array $activity): void`

Saves a 'Create' Activity (e.g. a remote post or reply) to the local inbox/comments.
Converts HTML content to Markdown, downloads avatars, and saves as a pending interaction.

@param array $activity The ActivityPub Create activity object.
@return void

### downloadAvatarLocally()
`private function downloadAvatarLocally(string $actorUrl, string $photoUrl): string`

Downloads an actor's avatar to the local cache.
Re-uses the cached version if already downloaded.

@param string $actorUrl The URL of the actor profile.
@param string $photoUrl The remote URL of the avatar image.
@return string The local path to the downloaded avatar.

### processOutgoingWebmentions()
`private function processOutgoingWebmentions(): void`

Processes outgoing webmentions.

### discoverWebmentionEndpoint()
`private function discoverWebmentionEndpoint(string $url): ?string`

Discovers a webmention endpoint from a target URL.

@param string $url The target URL.
@return string|null The endpoint URL or null if not found.

### resolveUrl()
`private function resolveUrl(string $base, string $rel): string`

Resolves a relative URL against a base URL.

### extractLinksToArchiveQueue()
`private function extractLinksToArchiveQueue(string $htmlContent): void`

Scans markdown content for external links and queues them for archiving.
Used to automatically snapshot outgoing links to the Wayback Machine.

@param string $htmlContent The content text to scan for links.
@return void

### getPublicKey()
`private function getPublicKey(string $keyId): ?string`

Retrieves the public key of an ActivityPub actor.
Caches the fetched key locally to speed up future signature verifications.

@param string $keyId The URL of the actor to fetch the key for.
@return string|null The PEM encoded public key, or null if not found.

### fetchJsonUrl()
`protected function fetchJsonUrl(string $url): ?array`

Fetches a URL and decodes the JSON response.
Assumes an ActivityPub/JSON-LD friendly Accept header.

@param string $url The URL to fetch.
@return array|null The decoded JSON array, or null on failure.

### fetchUrl()
`protected function fetchUrl(string $url)`

Method fetchUrl
@param string $url

### queueAcceptFollow()
`private function queueAcceptFollow(array $followActivity, string $targetInbox): void`

Queues an 'Accept' response to a 'Follow' activity.
Places the payload into the outbox for background delivery.

@param array $followActivity The received Follow activity data.
@param string $targetInbox The inbox URL to deliver the Accept activity to.

@return void

### processOutbox()
`public function processOutbox(): void`

Processes the outgoing queue (Outbox).
Delivers queued activities (e.g., Creates, Accepts) to followers' inboxes using HTTP Signatures.

@return void

### processArchiveQueue()
`public function processArchiveQueue(): void`

Processes the archive queue.
Submits external links to the Wayback Machine and optionally generates PDFs via Microlink.

@return void

### resolveFinalUrl()
`protected function resolveFinalUrl(string $url): string`

Follows HTTP redirects to resolve the final destination URL.
Prevents archiving tracking links or URL shorteners directly.

@param string $url The original URL to resolve.
@return string The final resolved URL.

### sendToArchiveOrg()
`protected function sendToArchiveOrg(string $url): void`

Submits a URL to the Internet Archive's Wayback Machine.

@param string $url The URL to archive.
@return void

### fetchPdfFromMicrolink()
`protected function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string`

Generates and downloads a PDF snapshot of a URL using the Microlink API.
Saves the PDF to the local archive directory.

@param string $url The URL to snapshot.
@param string $normUrl The normalized URL.
@param string $pdfDir The local directory to save the PDF.
@return string|null The relative path to the PDF, or null on failure.

### checkAkismet()
`private function checkAkismet(array $commentData): bool`

Checks if a submitted comment or webmention is spam using the Akismet API.

@param array $commentData The comment data containing 'author_name', 'author_url', and 'content'.
@return bool True if the content is classified as spam, false otherwise.
