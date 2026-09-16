# ModerationView
**Namespace:** `Indieinabox\Views\Admin`

Presentation view component that renders the administrative interaction moderation interface.

## Methods

### render()
`public static function render(array $pending, array $spam, string $fqdn): string`

Renders the moderation panel interface with pending comments and spam items.

@param array<int, array<string, mixed>> $pending List of pending interaction records.
@param array<int, array<string, mixed>> $spam List of flagged spam interaction records.
@param string $fqdn Fully qualified domain name.
@return string Rendered HTML.
