# GemtextRenderer
**Namespace:** `Indieinabox\Markdown`

## Properties

### `private array $links`

@var array<array{target: string, label: string}>

### `private ?Indieinabox\Page $page`

@var \Indieinabox\Page|null

## Methods

### __construct()
`public function __construct(?Indieinabox\Page $page = null)`

### render()
`public function render(Indieinabox\Markdown\Node $node): string`

Renders a Node AST to Gemini/Gemtext format.

@param Node $node
@return string

### renderNode()
`private function renderNode(Indieinabox\Markdown\Node $node): string`

### renderPlain()
`private function renderPlain(Indieinabox\Markdown\Node $node): string`
