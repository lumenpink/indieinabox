# PagePublisher
**Namespace:** `Indieinabox\SiteBuilder`

Handles rendering, compilation, and file publication of Page objects
across HTML, Gemini, and Gopher protocols.

## Properties

### `private Indieinabox\Site $site`

### `private Indieinabox\Pages $pages`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, Indieinabox\Pages $pages)`

### publish()
`public function publish(Indieinabox\Page $page): void`

Publishes a page across all supported output formats (HTML, Gemini, Gopher).

@param Page $page
@return void

### publishAll()
`public function publishAll(iterable $pages): void`

Publishes multiple pages across all supported formats.

@param iterable<Page> $pages
@return void

### publishHtml()
`public function publishHtml(Indieinabox\Page $page): void`

Renders a single Page object into an HTML file using the configured theme.
Handles slug resolution, metadata extraction, ActivityPub JSON, interactions, and shortlink generation.

@param Page $page The page to render.
@return void

### publishGemini()
`public function publishGemini(Indieinabox\Page $page): void`

Renders a page into Gemini Gemtext (.gmi) and writes it to the Gemini output directory.

@param Page $page The page to render.
@return void

### publishGopher()
`public function publishGopher(Indieinabox\Page $page): void`

Renders a page into Gopher format (gophermap) and writes it to the gopher output directory.
Formats links and metadata according to RFC 1436.

@param Page $page The page to render.
@return void

### getLanguageLinks()
`public function getLanguageLinks(Indieinabox\Page $page): array`

@return array<string, string>

### getMenuLinks()
`public function getMenuLinks(Indieinabox\Page $page): array`

@return array<string, array<int, array<string, mixed>>>
