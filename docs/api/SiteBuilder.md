# SiteBuilder
**Namespace:** `Indieinabox`

Class SiteBuilder

Orchestrates the static site generation process. It scans the content directory,
virtualizes missing translations, processes markdown into HTML/Gemtext/Gophermap,
and compiles feeds and assets into the output directory.

## Properties

### `private Indieinabox\Site $site`

### `private Indieinabox\Pages $pages`

### `private Indieinabox\ParserInterface $parser`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\Pages $pages = null, ?Indieinabox\ParserInterface $parser = null)`

### getPages()
`public function getPages(): Indieinabox\Pages`

### build()
`public function build(): void`

Executes the main build pipeline.

Cleans the output directory, scans content files, handles translation virtualization,
and triggers generation of HTML, feeds, and static assets.

### copyMedia()
`public function copyMedia(): void`

### virtualizeMissingLanguages()
`private function virtualizeMissingLanguages(): void`

### pseudoTranslate()
`public function pseudoTranslate(Indieinabox\Page $page, string $targetLang): void`

### scan()
`public function scan(string $dir): void`

### generateHTMLFiles()
`public function generateHTMLFiles(): void`

### createHTMLFile()
`private function createHTMLFile(Indieinabox\Page $page): void`

### generateFeed()
`public function generateFeed(): void`

### copyAssets()
`public function copyAssets(string $dir): void`

### copyStatic()
`public function copyStatic(string $dir): bool`

### copyLiveJsFile()
`private function copyLiveJsFile(string $base): void`

### createGeminiFile()
`private function createGeminiFile(Indieinabox\Page $page): void`

### createGopherFile()
`private function createGopherFile(Indieinabox\Page $page): void`

### generateTwtxt()
`public function generateTwtxt(): void`

### getLanguageLinks()
`private function getLanguageLinks(Indieinabox\Page $page): array`

@return array<string, string>

### getMenuLinks()
`private function getMenuLinks(Indieinabox\Page $page): array`

@return array<string, array<int, array<string, string>>>

### getKindFolder()
`private function getKindFolder(string $kind, string $lang): string`

### compileTimelineIndexes()
`private function compileTimelineIndexes(string $targetKind, array $pages): void`

@param \Indieinabox\Page[] $pages

### compileSitemap()
`private function compileSitemap(): void`

### compileSectionIndexes()
`private function compileSectionIndexes(string $targetKind, array $pages): void`

@param string $targetKind
@param array<int, Page> $pages
