# HtmlRenderer
**Namespace:** `Indieinabox\Markdown`

Class HtmlRenderer

## Properties

### `private ?Indieinabox\Page\Page $page`

@var \Indieinabox\Page\Page|null

### `private ?Indieinabox\Site\Site $site`

@var \Indieinabox\Site\Site|null

## Methods

### __construct()
`public function __construct(?Indieinabox\Page\Page $page = null, ?Indieinabox\Site\Site $site = null)`

### getSite()
`private function getSite(): ?Indieinabox\Site\Site`

### setPage()
`public function setPage(Indieinabox\Page\Page $page): void`

Set active page context.

@param \Indieinabox\Page\Page $page
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
