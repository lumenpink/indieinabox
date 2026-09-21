# ContentProcessor
**Namespace:** `Indieinabox\Markdown`

Class ContentProcessor

## Properties

### `private Indieinabox\Markdown\ASTParser $astParser`

@var ASTParser

### `private Indieinabox\Markdown\HtmlRenderer $htmlRenderer`

@var HtmlRenderer

## Methods

### __construct()
`public function __construct()`

Initializes the ContentProcessor.
Sets up the CommonMark environment with appropriate extensions
(e.g., frontmatter, headings, autolinks) to parse the markdown body.

### extractFrontMatter()
`public function extractFrontMatter(string $content): array`

@param string $content

@return array<string, mixed>

### removeYamlFrontMatter()
`public function removeYamlFrontMatter(string $content): ?string`

@param string $content

@return null|string

### setDate()
`public function setDate(array $page, string $file): array`

Set the date from file modification time if not provided in frontmatter.

@param array<string, mixed>  $page
@param string $file
@return array<string, mixed>

### setTitle()
`public function setTitle(array $page, string $content, string $defaultTitle): array`

@param array<string, mixed>  $page
@param string $content
@param string $defaultTitle

@return array<string, mixed>

### processTags()
`public function processTags(array $page, string $content): array`

@param array<string, mixed>  $page
@param string $content

@return array<string, mixed>

### processContent()
`public function processContent(string $content, ?Indieinabox\Page\Page $page = null, ?Indieinabox\Page\Pages $pages = null): string`

@param string $content
@param \Indieinabox\Page\Page|null $page
@param \Indieinabox\Page\Pages|null $pages

@return string

### addTrailingSlashesToInternalLinks()
`private function addTrailingSlashesToInternalLinks(string $content): ?string`

@param string $content

@return null|string
