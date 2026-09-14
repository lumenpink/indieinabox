# InboxProcessor
**Namespace:** `Indieinabox\BackgroundWorker`

Class InboxProcessor

Processes pending items in inbox_queue: Webmentions, ActivityPub activities,
and site build events.

## Properties

### `private Indieinabox\Site $site`

### `private PDO $db`

### `private mixed $fetcher`

@var callable|null

### `private mixed $jsonFetcher`

@var callable|null

### `private mixed $signatureVerifier`

@var callable|null

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, PDO $db, ?callable $fetcher = null, ?callable $jsonFetcher = null, ?callable $signatureVerifier = null)`

@param Site $site
@param PDO $db
@param callable|null $fetcher Optional HTTP fetcher hook fn(string $url): string|false
@param callable|null $jsonFetcher Optional JSON fetcher hook fn(string $url): ?array
@param callable|null $signatureVerifier Optional HTTP signature verifier hook

### process()
`public function process(): void`

Processes pending items in the inbox queue.

@return void

### handleBuildSite()
`public function handleBuildSite(): void`

Triggers a site rebuild.

@return void

### handleWebmention()
`public function handleWebmention(array $payload): void`

Parses and handles a received Webmention.

@param array $payload The Webmention data (source, target).
@return void

### handleActivityPub()
`public function handleActivityPub(array $payload): void`

Processes a received ActivityPub activity.

@param array $payload The parsed ActivityPub JSON-LD data.
@return void

### saveActivityPubCreate()
`public function saveActivityPubCreate(array $activity): void`

Saves a 'Create' Activity to the local inbox/comments.

@param array $activity The ActivityPub Create activity object.
@return void

### downloadAvatarLocally()
`public function downloadAvatarLocally(string $actorUrl, string $photoUrl): string`

Downloads an actor's avatar to the local cache.

@param string $actorUrl
@param string $photoUrl
@return string

### queueAcceptFollow()
`public function queueAcceptFollow(array $followActivity, string $targetInbox): void`

Queues an 'Accept' response to a 'Follow' activity.

@param array $followActivity
@param string $targetInbox
@return void

### extractLinksToArchiveQueue()
`public function extractLinksToArchiveQueue(string $htmlContent): void`

Extracts URLs from HTML content and queues them for web archiving.

@param string $htmlContent
@return void

### getPublicKey()
`public function getPublicKey(string $keyId): ?string`

Retrieves the public key of an ActivityPub actor.

@param string $keyId
@return string|null

### verifySignature()
`public function verifySignature(array $headers, string $method, string $path, string $pubKey): bool`

Verifies an HTTP signature on an incoming ActivityPub request.

@param array $headers
@param string $method
@param string $path
@param string $pubKey
@return bool

### checkAkismet()
`public function checkAkismet(array $commentData): bool`

Checks if a submitted comment or webmention is spam using the Akismet API.

@param array $commentData
@return bool

### fetchJsonUrl()
`public function fetchJsonUrl(string $url): ?array`

Fetches a URL and decodes the JSON response.

@param string $url
@return array|null

### fetchUrl()
`public function fetchUrl(string $url)`

Fetches the content of a remote URL.

@param string $url
@return string|false
