# HtmlRenderer
**Namespace:** `Indieinabox\Markdown`

## Properties

### `private ?Indieinabox\Page $page`

@var \Indieinabox\Page|null

## Methods

### setPage()
`public function setPage(Indieinabox\Page $page): void`

Set active page context.

@param \Indieinabox\Page $page
@return void

### getColors()
`private function getColors(): array`

Map active layout / kind to appropriate background and foreground colors.

@return array{bg: int[], fg: int[]}

### render()
`public function render(Indieinabox\Markdown\Node $node): string`

Recursively walks the AST and returns the generated HTML.

@param Node $node
@return string
