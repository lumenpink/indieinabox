# ArchiveView
**Namespace:** `Indieinabox\Views`

Class ArchiveView

Renders the HTML toolbar and viewer markup for archived snapshots.

## Methods

### render()
`public static function render(string $url, ?array $snapshot = null): string`

Renders the archive iframe toolbar and viewer HTML markup.

@param string $url Target URL.
@param array<string, mixed>|null $snapshot Snapshot row if found.
@return string
