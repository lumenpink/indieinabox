# ContentProcessor
**Namespace:** `Indieinabox\Markdown`

## Properties

### `private Indieinabox\Markdown\ASTParser $astParser`

@var ASTParser

### `private Indieinabox\Markdown\HtmlRenderer $htmlRenderer`

@var HtmlRenderer

## Methods

### __construct()
`public function __construct()`

### extractFrontMatter()
`public function extractFrontMatter(string $content): array`

@param string $content

@return array<string, mixed>

### removeYamlFrontMatter()
`public function removeYamlFrontMatter(string $content): string`

@param string $content

@return string

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
`public function processContent(string $content, ?Indieinabox\Page $page = null): string`

@param string $content
@param \Indieinabox\Page|null $page

@return string

### addTrailingSlashesToInternalLinks()
`private function addTrailingSlashesToInternalLinks(string $content): string`

@param string $content

@return string
