# FederationAdapter
**Namespace:** `Indieinabox\Federation\Contracts`

Interface defining protocol adapters for federated networks.

## Methods

### getProtocol()
`abstract public function getProtocol(): string`

Unique protocol identifier (e.g. 'activitypub', 'twtxt', 'lemmy').

### supports()
`abstract public function supports(string $protocol): bool`

Checks if this adapter handles the specified network or protocol.

### buildLikeActivity()
`abstract public function buildLikeActivity(string $targetUrl): array`

Builds a like/favorite activity payload for the target object.

@param string $targetUrl
@return array<string, mixed>

### buildReplyActivity()
`abstract public function buildReplyActivity(string $targetUrl, string $content, ?string $inReplyTo = null): array`

Builds a reply/comment activity payload.

@param string $targetUrl
@param string $content
@param string|null $inReplyTo
@return array<string, mixed>

### buildFollowActivity()
`abstract public function buildFollowActivity(string $targetActorUri): array`

Builds a follow request activity payload for a target actor.

@param string $targetActorUri
@return array<string, mixed>

### deliverActivity()
`abstract public function deliverActivity(array|string $activity, string $destinationUrl): bool`

Delivers an activity payload to a remote recipient endpoint.

@param array<string, mixed>|string $activity
@param string $destinationUrl
@return bool

### parseActivity()
`abstract public function parseActivity(string $payload): ?array`

Parses an incoming raw activity payload into a normalized array.

@param string $payload
@return array<string, mixed>|null
