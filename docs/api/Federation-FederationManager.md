# FederationManager
**Namespace:** `Indieinabox\Federation`

Registry and orchestrator for protocol adapters (ActivityPub, Twtxt, Lemmy, etc.).

## Properties

### `private Indieinabox\Site $site`

### `private array $adapters`

@var array<string, FederationAdapter>

## Methods

### __construct()
`public function __construct(?Indieinabox\Site $site = null)`

### registerDefaultAdapters()
`private function registerDefaultAdapters(): void`

### register()
`public function register(Indieinabox\Federation\Contracts\FederationAdapter $adapter): void`

Registers a protocol adapter.

### has()
`public function has(string $protocol): bool`

Checks if an adapter is available for a protocol.

### get()
`public function get(string $protocol): Indieinabox\Federation\Contracts\FederationAdapter`

Resolves an adapter for the specified protocol.

### all()
`public function all(): array`

Returns all registered adapters.

@return array<string, FederationAdapter>
