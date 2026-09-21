# ActivityPubController
**Namespace:** `Indieinabox\Http\Controllers`

Controller managing HTTP endpoints for ActivityPub federation, actor discovery, and inbox/outbox.

## Properties

### `private PDO $db`

### `private Indieinabox\ActivityPub\KeyManager $keyManager`

### `private Indieinabox\ActivityPub\InteractionHandler $interactionHandler`

### `protected Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, ?PDO $db = null, ?Indieinabox\ActivityPub\KeyManager $keyManager = null, ?Indieinabox\ActivityPub\InteractionHandler $interactionHandler = null)`

### getKeyManager()
`public function getKeyManager(): Indieinabox\ActivityPub\KeyManager`

### getInteractionHandler()
`public function getInteractionHandler(): Indieinabox\ActivityPub\InteractionHandler`

### interact()
`public function interact(): void`

Handles /interact route.

### authorizeInteraction()
`public function authorizeInteraction(): void`

Handles /authorize_interaction route.

### webfinger()
`public function webfinger(): void`

Handles WebFinger (.well-known/webfinger) requests for actor discovery.

### actor()
`public function actor(): void`

Outputs the ActivityPub Actor profile (Person) in JSON-LD format.

### inbox()
`public function inbox(): void`

Handles incoming activities (POST to /inbox).

### outbox()
`public function outbox(): void`

Handles GET requests to the outbox (/outbox).

### getSite()
`public function getSite(): Indieinabox\Site\Site`

### json()
`protected function json(?mixed $data, int $status = 200, array $headers = []): void`

Emits a JSON response.

@param mixed $data
@param int $status
@param array<string, string> $headers

### html()
`protected function html(string $html, int $status = 200, array $headers = []): void`

Emits an HTML response.

@param string $html
@param int $status
@param array<string, string> $headers

### redirect()
`protected function redirect(string $url, int $status = 302): void`

Emits a redirect header.

### status()
`protected function status(int $status): void`

Sets HTTP status code.

### jsonResponse()
`protected function jsonResponse(array $data, int $status = 200, array $headers = []): void`

@param array<string, string> $headers
@param (int|string|string[])[] $data

@psalm-param array{error?: string, error_description?: string, issuer?: string, authorization_endpoint?: string, token_endpoint?: string, response_types_supported?: list{'code'}, grant_types_supported?: list{'authorization_code'}, code_challenge_methods_supported?: list{'S256', 'plain'}, me?: string, scope?: string, access_token?: string, token_type?: string, client_id?: string, status?: int, message?: string} $data

### htmlResponse()
`protected function htmlResponse(string $html, int $status = 200, array $headers = []): void`

@param array<string, string> $headers

### redirectResponse()
`protected function redirectResponse(string $url, int $status = 302): void`
