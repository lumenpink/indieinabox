# ConfigHandler
**Namespace:** `Indieinabox`

## Properties

### `private Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### handle()
`public function handle(): void`

### handleBootstrap()
`private function handleBootstrap(): void`

### handleCallback()
`private function handleCallback(): void`

### redirectToAuth()
`private function redirectToAuth(): void`

### saveConfig()
`private function saveConfig(): void`

### rebuildSite()
`private function rebuildSite(): void`

### detectPrettyLinksSupport()
`private function detectPrettyLinksSupport(): bool`

### renderBootstrapForm()
`private function renderBootstrapForm(?string $error = null): void`

### renderConfigForm()
`private function renderConfigForm(): void`

### sendError()
`private function sendError(int $code, string $message): void`
