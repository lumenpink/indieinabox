# ActivityPubHandler
**Namespace:** `Indieinabox`

Class ActivityPubHandler

Orchestrates ActivityPub server endpoints (WebFinger, Actor, Inbox, Outbox)
and delegates domain-specific logic to KeyManager, ActivityBuilder, and InteractionHandler.

## Properties

### `private Indieinabox\Site $site`

@var Site Global site configuration and environment.

### `private PDO $db`

@var PDO Database connection.

### `private Indieinabox\ActivityPub\KeyManager $keyManager`

@var KeyManager RSA key manager.

### `private Indieinabox\ActivityPub\InteractionHandler $interactionHandler`

@var InteractionHandler Federated interaction handler.

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?PDO $db = null, ?Indieinabox\ActivityPub\KeyManager $keyManager = null, ?Indieinabox\ActivityPub\InteractionHandler $interactionHandler = null)`

Initializes the ActivityPubHandler, binds dependencies, and ensures cryptographic keys exist.

@param Site $site Global site configuration.
@param ?PDO $db Database connection.
@param ?KeyManager $keyManager Key manager service.
@param ?InteractionHandler $interactionHandler Interaction handler service.

### ensureKeys()
`public function ensureKeys(): void`

Ensures an RSA key pair exists for signing ActivityPub payloads.

@return void

### handleWebFinger()
`public function handleWebFinger(): void`

Handles WebFinger (.well-known/webfinger) requests for actor discovery.
Returns a JSON JRD (JSON Resource Descriptor) mapping the requested alias to the actor profile.

@return void

### handleActor()
`public function handleActor(): void`

Outputs the ActivityPub Actor profile (Person) in JSON-LD format.
Defines inbox, outbox, public keys, and other identifying metadata.

@return void

### handleInbox()
`public function handleInbox(): void`

Handles incoming activities (POST to /inbox).
Enqueues incoming activities into inbox_queue for asynchronous background processing.

@return void

### queueAcceptFollow()
`public function queueAcceptFollow(array $followActivity, string $targetInbox): void`

Queues an Accept activity in response to a received Follow activity.
Stores the intent in the outbox queue to be processed asynchronously.

@param array<string, mixed> $followActivity The received Follow activity payload.
@param string $targetInbox The inbox URL of the actor who sent the Follow request.
@return void

### handleOutbox()
`public function handleOutbox(): void`

Handles GET requests to the outbox (/outbox).
Returns an empty OrderedCollection by default.

@return void

### buildObjectForPageArray()
`public static function buildObjectForPageArray(string $objectId, string $actorId, string $fqdn, string $content, ?string $name, array $metadata = []): array`

Builds an ActivityStreams Note or Article object array for a page or post.

@param string $objectId Unique object IRI.
@param string $actorId Local actor IRI.
@param string $fqdn Fully qualified domain name.
@param string $content Post content.
@param ?string $name Post title.
@param array<string, mixed> $metadata Post frontmatter metadata.
@return array<string, mixed>

### queueCreateActivity()
`public function queueCreateActivity(string $postUrl, string $content, ?string $name, array $metadata = []): void`

Queues a Create activity for a new post and broadcasts it to all followers.

@param string $postUrl The public URL of the new post.
@param string $content The HTML content of the post.
@param ?string $name The title of the post (if applicable).
@param array<string, mixed> $metadata Metadata array.
@return void

### handleInteract()
`public function handleInteract(): void`

Handles /interact route.

@return void

### handleAuthorizeInteraction()
`public function handleAuthorizeInteraction(): void`

Handles /authorize_interaction route.

@return void
