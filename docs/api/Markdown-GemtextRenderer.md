# GemtextRenderer
**Namespace:** `Indieinabox\Markdown`

Class GemtextRenderer

## Properties

### `private array $links`

@var array<array{target: string, label: string}>

### `private ?Indieinabox\Page $page`

@var \Indieinabox\Page|null

## Methods

### __construct()
`public function __construct(?Indieinabox\Page $page = null)`

Method __construct
@param ?\Indieinabox\Page $page

### render()
`public function render(Indieinabox\Markdown\Node $node): string`

Renders a Node AST to Gemini/Gemtext format.

@param Node $node
@return string

### renderNode()
`private function renderNode(Indieinabox\Markdown\Node $node): string`

Method renderNode
@param \Indieinabox\Markdown\Node $node

@return string

### renderPlain()
`private function renderPlain(Indieinabox\Markdown\Node $node): string`

Method renderPlain
@param \Indieinabox\Markdown\Node $node

@return string
