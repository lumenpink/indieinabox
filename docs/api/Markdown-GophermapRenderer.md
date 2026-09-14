# GophermapRenderer
**Namespace:** `Indieinabox\Markdown`

Class GophermapRenderer

## Properties

### `private array $links`

@var array<array{type: string, label: string, target: string}>

### `private string $host`

@var string

### `private int $port`

@var int

### `private ?Indieinabox\Page $page`

@var \Indieinabox\Page|null

## Methods

### __construct()
`public function __construct(string $host = 'gopher.example.com', int $port = 70, ?Indieinabox\Page $page = null)`

Initializes the GophermapRenderer.

@param string $host The hostname to use for internal Gopher links.
@param int|string $port The port number to use for internal Gopher links.
@param \Indieinabox\Page|null $page The page being rendered, used for resolving relative links.

### render()
`public function render(Indieinabox\Markdown\Node $node): string`

Renders a Node AST to Gophermap format.

@param Node $node
@return string

### formatLine()
`private function formatLine(string $type, string $display, string $selector = '', string $host = '(null)', int $port = 0): string`

Formats a line of text into a valid Gophermap entry.

@param string $type The Gopher item type character (e.g., 'i' for info, '1' for directory, 'h' for HTML).
@param string $display The text to display to the user.
@param string $selector The path or selector for the resource.
@param string|null $host The target hostname (defaults to this renderer's host if null).
@param int|string|null $port The target port (defaults to this renderer's port if null).
@return string The formatted Gophermap line, terminated with CRLF.

### renderNode()
`private function renderNode(Indieinabox\Markdown\Node $node): string`

Recursively renders an AST node into Gophermap format.
Handles specific node types like headings, lists, quotes, and links,
ensuring proper wrapping and RFC 1436 formatting.

@param \Indieinabox\Markdown\Node $node The AST node to render.
@return string The rendered Gophermap string.

### renderPlain()
`private function renderPlain(Indieinabox\Markdown\Node $node): string`

Renders an AST node as plain text, stripping out any formatting.
Used for contexts where formatting is not supported (e.g., inside link labels).

@param \Indieinabox\Markdown\Node $node The AST node to render.
@return string The plain text representation.
