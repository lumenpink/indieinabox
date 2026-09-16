# AdminLayoutView
**Namespace:** `Indieinabox\Views\Admin`

Presentation view component that renders the administrative shell layout and navigation sidebar.

## Methods

### render()
`public static function render(string $content, string $activeTab = 'microsub', string $fqdn = ''): string`

Renders the administrative dashboard layout wrapping the inner view content.

@param string $content HTML content to render inside the main viewport.
@param string $activeTab Currently active navigation tab (e.g. 'microsub', 'moderation', 'micropub', 'config').
@param string $fqdn Fully qualified domain name of the site.
@return string Rendered HTML layout.
