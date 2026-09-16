# GemtextRenderer
**Namespace:** `Indieinabox\Markdown`

Class GemtextRenderer

## Properties

### `private array $links`

@var array<array{target: string, label: string}>

### `private ?Indieinabox\Page\Page $page`

@var \Indieinabox\Page\Page|null

## Methods

### __construct()
`public function __construct(?Indieinabox\Page\Page $page = null)`

Initializes the GemtextRenderer with the current page context.

@param \Indieinabox\Page\Page|null $page The page being rendered, used for resolving relative links.

### render()
`public function render(Indieinabox\Markdown\Node $node): string`

Renders a Node AST to Gemini/Gemtext format.

@param Node $node
@return string

### renderNode()
`private function renderNode(Indieinabox\Markdown\Node $node): string`

Recursively renders an AST node into Gemtext format.
Handles specific node types like headings, lists, quotes, and links.

@param Node $node The AST node to render.
@return string The rendered Gemtext string.

### renderPlain()
`private function renderPlain(Indieinabox\Markdown\Node $node): string`

Renders an AST node as plain text, stripping out any formatting.
Used for contexts where formatting is not supported (e.g., inside links).

@param Node $node The AST node to render.
@return string The plain text representation.
