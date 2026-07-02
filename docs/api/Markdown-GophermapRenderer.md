# GophermapRenderer
**Namespace:** `Indieinabox\Markdown`

## Properties

### `private array $links`

@var array<array{type: string, label: string, target: string}>

### `private string $host`

### `private int $port`

### `private ?Indieinabox\Page $page`

@var \Indieinabox\Page|null

## Methods

### __construct()
`public function __construct(string $host = 'gopher.example.com', int $port = 70, ?Indieinabox\Page $page = null)`

### render()
`public function render(Indieinabox\Markdown\Node $node): string`

Renders a Node AST to Gophermap format.

@param Node $node
@return string

### formatLine()
`private function formatLine(string $type, string $display, string $selector = '', string $host = '(null)', int $port = 0): string`

### renderNode()
`private function renderNode(Indieinabox\Markdown\Node $node): string`

### renderPlain()
`private function renderPlain(Indieinabox\Markdown\Node $node): string`
