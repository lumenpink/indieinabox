# InteractionHandler
**Namespace:** `Indieinabox\ActivityPub`

Class InteractionHandler

Handles client-side Fediverse interactions (/interact and /authorize_interaction).

## Properties

### `private Indieinabox\Site\Site $site`

@var Site Global site instance.

### `private PDO $db`

@var PDO Database connection.

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, ?PDO $db = null)`

InteractionHandler constructor.

@param Site $site Site instance.
@param ?PDO $db Optional PDO connection.

### handleInteract()
`public function handleInteract(): void`

Handles /interact route.
Displays a form for entering remote instance/handle and redirects to authorize_interaction.

@return void

### extractDomain()
`public function extractDomain(string $handleOrDomain): string`

Extracts domain name from a handle (@user@domain) or host string.

@param string $handleOrDomain
@return string

### renderInteractHtml()
`public function renderInteractHtml(string $uri): string`

Renders the HTML form for /interact.

@param string $uri
@return string

### handleAuthorizeInteraction()
`public function handleAuthorizeInteraction(): void`

Handles /authorize_interaction route.
Acts as the local Indieinabox client for remote interaction.

@return void

### processInteraction()
`public function processInteraction(string $action, string $uri, string $actorId, string $remoteActorUrl, string $inbox, bool $createLocal): void`

Processes confirmed interaction by building payload, saving local post if desired, and queueing outbox.

@param string $action 'Like', 'Announce', or 'Create'
@param string $uri Remote object URI
@param string $actorId Local actor URI
@param string $remoteActorUrl Remote actor URI
@param string $inbox Remote actor inbox
@param bool $createLocal Whether to save a local markdown file
@return void

### triggerSiteBuild()
`protected function triggerSiteBuild(): void`

Triggers static site rebuild if SiteBuilder exists.

@return void

### fetchRemoteJson()
`public function fetchRemoteJson(string $url): ?array`

Fetches remote JSON-LD/ActivityStreams object or actor via HTTP.

@param string $url
@return ?array<string, mixed>

### renderAuthorizeHtml()
`public function renderAuthorizeHtml(string $uri): string`

Renders HTML form for /authorize_interaction.

@param string $uri
@return string
