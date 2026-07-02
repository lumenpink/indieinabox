# BackgroundWorker
**Namespace:** `Indieinabox`

## Properties

### `private PDO $db`

### `private Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### runAll()
`public function runAll(): void`

### processInboxQueue()
`public function processInboxQueue(): void`

### handleBuildSite()
`private function handleBuildSite(): void`

### handleWebmention()
`private function handleWebmention(array $payload): void`

### handleActivityPub()
`private function handleActivityPub(array $payload): void`

### verifySignature()
`protected function verifySignature(array $headers, string $method, string $path, string $pubKey): bool`

### saveActivityPubCreate()
`private function saveActivityPubCreate(array $activity): void`

### downloadAvatarLocally()
`private function downloadAvatarLocally(string $actorUrl, string $photoUrl): string`

### extractLinksToArchiveQueue()
`private function extractLinksToArchiveQueue(string $htmlContent): void`

### getPublicKey()
`private function getPublicKey(string $keyId): ?string`

### fetchJsonUrl()
`protected function fetchJsonUrl(string $url): ?array`

### fetchUrl()
`protected function fetchUrl(string $url)`

### queueAcceptFollow()
`private function queueAcceptFollow(array $followActivity, string $targetInbox): void`

### processOutbox()
`public function processOutbox(): void`

### processArchiveQueue()
`public function processArchiveQueue(): void`

### resolveFinalUrl()
`protected function resolveFinalUrl(string $url): string`

### sendToArchiveOrg()
`protected function sendToArchiveOrg(string $url): void`

### fetchPdfFromMicrolink()
`protected function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string`
