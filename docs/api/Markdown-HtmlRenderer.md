# HtmlRenderer
**Namespace:** `Indieinabox\Markdown`

Class HtmlRenderer

## Properties

### `private ?Indieinabox\Page\Page $page`

@var \Indieinabox\Page\Page|null

### `private ?Indieinabox\Site\Site $site`

@var \Indieinabox\Site\Site|null

### `private ?Indieinabox\Page\Pages $pages`

@var \Indieinabox\Page\Pages|null

## Methods

### __construct()
`public function __construct(?Indieinabox\Page\Page $page = null, ?Indieinabox\Site\Site $site = null, ?Indieinabox\Page\Pages $pages = null)`

### getSite()
`private function getSite(): ?Indieinabox\Site\Site`

### getPages()
`private function getPages(): ?Indieinabox\Page\Pages`

### setPages()
`public function setPages(Indieinabox\Page\Pages $pages): void`

Set active page collection context.

@param \Indieinabox\Page\Pages $pages
@return void

### setPage()
`public function setPage(Indieinabox\Page\Page $page): void`

Set active page context.

@param \Indieinabox\Page\Page $page
@return void

### getColors()
`private function getColors(): array`

Map active layout / kind to appropriate background and foreground colors.

@return ((float|int|null|string)[]|null)[]

@psalm-return array{bg: list{0?: float|int|null|string, 1?: float|int|null|string, 2?: float|int|null|string,...}|null, fg: list{0?: float|int|null|string, 1?: float|int|null|string, 2?: float|int|null|string,...}|null}

### render()
`public function render(Indieinabox\Markdown\Node $node): string`

Recursively walks the AST and returns the generated HTML.

@param Node $node
@return string
