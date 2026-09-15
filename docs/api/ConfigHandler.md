# ConfigHandler
**Namespace:** `Indieinabox`

Class ConfigHandler

## Properties

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

### `private Indieinabox\Services\ConfigurationService $configService`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\Services\ConfigurationService $configService = null)`

Initializes the ConfigHandler with the site context.

@param \Indieinabox\Site $site Global site configuration and environment.
@param ?\Indieinabox\Services\ConfigurationService $configService Optional configuration service.

### handle()
`public function handle(): void`

Main handler entry point.
Manages bootstrapping, login, callbacks, configuration saving, and rendering
the admin configuration panel based on the request method and session state.

@return void

### handleBootstrap()
`private function handleBootstrap(): void`

Handles the initial bootstrapping of a new site installation.
Creates an admin user, hashes their password, and saves initial metadata.

@return void

### handleCallback()
`private function handleCallback(): void`

Processes the IndieAuth callback payload.
Verifies the OAuth state and authorization code with the authorization endpoint
to authenticate the admin user.

@return void

### redirectToAuth()
`private function redirectToAuth(): void`

Redirects the user to the IndieAuth authorization endpoint.
Generates PKCE challenges, stores the state in the session, and initiates the OAuth flow.

@return void

### saveConfig()
`private function saveConfig(): void`

Processes form submissions from the configuration panel.
Validates and saves metadata, options, themes, feeds, plugins, and kinds
into the database. Also triggers a site rebuild upon saving.

@return void

### recursiveDeleteDir()
`private function recursiveDeleteDir(string $dir): bool`

### installThemeFromUrl()
`private function installThemeFromUrl(string $url): void`

### installThemeFromZip()
`private function installThemeFromZip(string $zipPath): void`

### rebuildSite()
`private function rebuildSite(): void`

Triggers a site rebuild in the background by calling the CLI build script.
Returns early while the build process runs asynchronously.

@return void

### detectPrettyLinksSupport()
`private function detectPrettyLinksSupport(): bool`

Detects if the web server supports "pretty links" (URL rewriting).
Usually checks the presence of Apache's mod_rewrite via server variables.

@return bool True if pretty links are supported, false otherwise.

### renderBootstrapForm()
`private function renderBootstrapForm(?string $error = null): void`

Renders the bootstrap (first-run) HTML form.
Prompts the user for a password, site title, and fully qualified domain name.

@param string|null $error Optional error message to display on the form.
@return void

### renderConfigForm()
`private function renderConfigForm(): void`

Renders the main configuration form inside the admin panel.
Displays fields for metadata, kinds, translations, plugins, and shortlink settings.
Uses output buffering and includes the global admin layout.

@return void

### sendError()
`private function sendError(int $code, string $message): void`

Sends a plain-text HTTP error response.

@param int $code The HTTP status code (e.g., 400, 401, 500).
@param string $message The error message to display.
@return void
