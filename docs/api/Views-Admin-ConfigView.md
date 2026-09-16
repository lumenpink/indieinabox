# ConfigView
**Namespace:** `Indieinabox\Views\Admin`

Presentation view component that renders the configuration and bootstrap views.

## Methods

### renderBootstrap()
`public static function renderBootstrap(?string $error = null): string`

Renders the bootstrap first-run setup form.

### renderConfig()
`public static function renderConfig(Indieinabox\Site $site, array $config, ?string $message = null, ?string $error = null): string`

Renders the main administration configuration form tabs.

@param Site $site
@param array<string, mixed> $config
@param string|null $message
@param string|null $error
@return string
