# BackgroundWorker
**Namespace:** `Indieinabox`

Class BackgroundWorker

## Properties

### `private PDO $db`

@var PDO

### `private Indieinabox\Site $site`

@var Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Method __construct
@param Indieinabox\Site $site

### runAll()
`public function runAll(): void`

Method runAll
@return void

### processInboxQueue()
`public function processInboxQueue(): void`

Method processInboxQueue
@return void

### handleBuildSite()
`private function handleBuildSite(): void`

Method handleBuildSite
@return void

### handleWebmention()
`private function handleWebmention(array $payload): void`

Method handleWebmention
@param array $payload

@return void

### handleActivityPub()
`private function handleActivityPub(array $payload): void`

Method handleActivityPub
@param array $payload

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

Method saveActivityPubCreate
@param array $activity

@return void

### downloadAvatarLocally()
`private function downloadAvatarLocally(string $actorUrl, string $photoUrl): string`

Method downloadAvatarLocally
@param string $actorUrl
@param string $photoUrl

@return string

### extractLinksToArchiveQueue()
`private function extractLinksToArchiveQueue(string $htmlContent): void`

Method extractLinksToArchiveQueue
@param string $htmlContent

@return void

### getPublicKey()
`private function getPublicKey(string $keyId): ?string`

Method getPublicKey
@param string $keyId

@return ?string

### fetchJsonUrl()
`protected function fetchJsonUrl(string $url): ?array`

Method fetchJsonUrl
@param string $url

@return ?array

### fetchUrl()
`protected function fetchUrl(string $url)`

Method fetchUrl
@param string $url

### queueAcceptFollow()
`private function queueAcceptFollow(array $followActivity, string $targetInbox): void`

Method queueAcceptFollow
@param array $followActivity
@param string $targetInbox

@return void

### processOutbox()
`public function processOutbox(): void`

Method processOutbox
@return void

### processArchiveQueue()
`public function processArchiveQueue(): void`

Method processArchiveQueue
@return void

### resolveFinalUrl()
`protected function resolveFinalUrl(string $url): string`

Method resolveFinalUrl
@param string $url

@return string

### sendToArchiveOrg()
`protected function sendToArchiveOrg(string $url): void`

Method sendToArchiveOrg
@param string $url

@return void

### fetchPdfFromMicrolink()
`protected function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string`

Method fetchPdfFromMicrolink
@param string $url
@param string $normUrl
@param string $pdfDir

@return ?string
