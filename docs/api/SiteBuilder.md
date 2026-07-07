# SiteBuilder
**Namespace:** `Indieinabox`

Class SiteBuilder

Orchestrates the static site generation process. It scans the content directory,
virtualizes missing translations, processes markdown into HTML/Gemtext/Gophermap,
and compiles feeds and assets into the output directory.

## Properties

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

### `private Indieinabox\Pages $pages`

@var \Indieinabox\Pages

### `private Indieinabox\ParserInterface $parser`

@var \Indieinabox\ParserInterface

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\Pages $pages = null, ?Indieinabox\ParserInterface $parser = null)`

Method __construct
@param \Indieinabox\Site $site
@param ?\Indieinabox\Pages $pages
@param ?\Indieinabox\ParserInterface $parser

### getPages()
`public function getPages(): Indieinabox\Pages`

Method getPages
@return \Indieinabox\Pages

### build()
`public function build(): void`

Executes the main build pipeline.

Cleans the output directory, scans content files, handles translation virtualization,
and triggers generation of HTML, feeds, and static assets.

### copyMedia()
`public function copyMedia(): void`

Method copyMedia
@return void

### virtualizeMissingLanguages()
`private function virtualizeMissingLanguages(): void`

Method virtualizeMissingLanguages
@return void

### pseudoTranslate()
`public function pseudoTranslate(Indieinabox\Page $page, string $targetLang): void`

Method pseudoTranslate
@param \Indieinabox\Page $page
@param string $targetLang

@return void

### scan()
`public function scan(string $dir): void`

Method scan
@param string $dir

@return void

### generateHTMLFiles()
`public function generateHTMLFiles(): void`

Method generateHTMLFiles
@return void

### createHTMLFile()
`private function createHTMLFile(Indieinabox\Page $page): void`

Method createHTMLFile
@param \Indieinabox\Page $page

@return void

### generateFeed()
`public function generateFeed(): void`

Method generateFeed
@return void

### copyAssets()
`public function copyAssets(string $dir): void`

Method copyAssets
@param string $dir

@return void

### copyStatic()
`public function copyStatic(string $dir): bool`

Method copyStatic
@param string $dir

@return bool

### copyLiveJsFile()
`private function copyLiveJsFile(string $base): void`

Method copyLiveJsFile
@param string $base

@return void

### createGeminiFile()
`private function createGeminiFile(Indieinabox\Page $page): void`

Method createGeminiFile
@param \Indieinabox\Page $page

@return void

### createGopherFile()
`private function createGopherFile(Indieinabox\Page $page): void`

Method createGopherFile
@param \Indieinabox\Page $page

@return void

### generateTwtxt()
`public function generateTwtxt(): void`

Method generateTwtxt
@return void

### getLanguageLinks()
`private function getLanguageLinks(Indieinabox\Page $page): array`

@return array<string, string>

### getMenuLinks()
`private function getMenuLinks(Indieinabox\Page $page): array`

@return array<string, array<int, array<string, string>>>

### getKindFolder()
`private function getKindFolder(string $kind, string $lang): string`

Method getKindFolder
@param string $kind
@param string $lang

@return string

### compileTimelineIndexes()
`private function compileTimelineIndexes(string $targetKind, array $pages): void`

@param \Indieinabox\Page[] $pages

### compileSitemap()
`private function compileSitemap(): void`

Method compileSitemap
@return void

### compileSectionIndexes()
`private function compileSectionIndexes(string $targetKind, array $pages): void`

@param string $targetKind
@param array<int, Page> $pages

### ensureMandatoryHomepage()
`private function ensureMandatoryHomepage(): void`

Method ensureMandatoryHomepage
@return void
