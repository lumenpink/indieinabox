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

Method __construct
@param string $host
@param int $port
@param ?\Indieinabox\Page $page

### render()
`public function render(Indieinabox\Markdown\Node $node): string`

Renders a Node AST to Gophermap format.

@param Node $node
@return string

### formatLine()
`private function formatLine(string $type, string $display, string $selector = '', string $host = '(null)', int $port = 0): string`

Method formatLine
@param string $type
@param string $display
@param string $selector
@param string $host
@param int $port

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
