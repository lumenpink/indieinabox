# ConfigHandler
**Namespace:** `Indieinabox`

Class ConfigHandler

## Properties

### `private Indieinabox\Site $site`

@var Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Method __construct
@param Indieinabox\Site $site

### handle()
`public function handle(): void`

Method handle
@return void

### handleBootstrap()
`private function handleBootstrap(): void`

Method handleBootstrap
@return void

### handleCallback()
`private function handleCallback(): void`

Method handleCallback
@return void

### redirectToAuth()
`private function redirectToAuth(): void`

Method redirectToAuth
@return void

### saveConfig()
`private function saveConfig(): void`

Method saveConfig
@return void

### rebuildSite()
`private function rebuildSite(): void`

Method rebuildSite
@return void

### detectPrettyLinksSupport()
`private function detectPrettyLinksSupport(): bool`

Method detectPrettyLinksSupport
@return bool

### renderBootstrapForm()
`private function renderBootstrapForm(?string $error = null): void`

Method renderBootstrapForm
@param ?string $error

@return void

### renderConfigForm()
`private function renderConfigForm(): void`

Method renderConfigForm
@return void

### sendError()
`private function sendError(int $code, string $message): void`

Method sendError
@param int $code
@param string $message

@return void
