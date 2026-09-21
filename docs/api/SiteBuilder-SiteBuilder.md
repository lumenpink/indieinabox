# SiteBuilder
**Namespace:** `Indieinabox\SiteBuilder`

Class SiteBuilder

Orchestrates the static site generation process. It coordinates scanning,
translation virtualization, content rendering, feed generation, and asset publishing.

## Properties

### `private Indieinabox\Site\Site $site`

@var \Indieinabox\Site\Site

### `private Indieinabox\Page\Pages $pages`

@var \Indieinabox\Page\Pages

### `private Indieinabox\Markdown\ParserInterface $parser`

@var \Indieinabox\Markdown\ParserInterface

### `private Indieinabox\SiteBuilder\ContentScanner $contentScanner`

@var \Indieinabox\SiteBuilder\ContentScanner

### `private Indieinabox\SiteBuilder\AssetPublisher $assetPublisher`

@var \Indieinabox\SiteBuilder\AssetPublisher

### `private Indieinabox\SiteBuilder\FeedPublisher $feedPublisher`

@var \Indieinabox\SiteBuilder\FeedPublisher

### `private Indieinabox\SiteBuilder\PagePublisher $pagePublisher`

@var \Indieinabox\SiteBuilder\PagePublisher

### `private Indieinabox\SiteBuilder\TranslationVirtualizer $translationVirtualizer`

@var \Indieinabox\SiteBuilder\TranslationVirtualizer

### `private Indieinabox\SiteBuilder\IndexPublisher $indexPublisher`

@var \Indieinabox\SiteBuilder\IndexPublisher

### `private Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface $taxonomyService`

### `public static array $manifest`

Stores absolute paths of all generated files during the build process
for Garbage Collection.
@var array<string, bool>

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, ?Indieinabox\Page\Pages $pages = null, ?Indieinabox\Markdown\ParserInterface $parser = null, ?Indieinabox\SiteBuilder\AssetPublisher $assetPublisher = null, ?Indieinabox\SiteBuilder\FeedPublisher $feedPublisher = null, ?Indieinabox\SiteBuilder\PagePublisher $pagePublisher = null, ?Indieinabox\SiteBuilder\TranslationVirtualizer $translationVirtualizer = null, ?Indieinabox\SiteBuilder\IndexPublisher $indexPublisher = null, ?Indieinabox\SiteBuilder\ContentScanner $contentScanner = null, ?Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface $taxonomyService = null)`

SiteBuilder constructor.

@param \Indieinabox\Site\Site $site The site configuration and environment settings.
@param \Indieinabox\Page\Pages|null $pages An optional collection of parsed pages.
@param \Indieinabox\Markdown\ParserInterface|null $parser An optional markdown parser implementation.
@param \Indieinabox\SiteBuilder\AssetPublisher|null $assetPublisher An optional asset publisher.
@param \Indieinabox\SiteBuilder\FeedPublisher|null $feedPublisher An optional feed publisher.
@param \Indieinabox\SiteBuilder\PagePublisher|null $pagePublisher An optional page publisher.
@param \Indieinabox\SiteBuilder\TranslationVirtualizer|null $translationVirtualizer An optional translation virtualizer.
@param \Indieinabox\SiteBuilder\IndexPublisher|null $indexPublisher An optional index publisher.
@param \Indieinabox\SiteBuilder\ContentScanner|null $contentScanner An optional content scanner.
@param \Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface|null $taxonomyService An optional taxonomy service.

### getPages()
`public function getPages(): Indieinabox\Page\Pages`

Retrieves the collection of processed pages.

@return \Indieinabox\Page\Pages The pages collection.

### getParser()
`public function getParser(): Indieinabox\Markdown\ParserInterface`

Retrieves the markdown parser implementation.

@return \Indieinabox\Markdown\ParserInterface

### getContentScanner()
`public function getContentScanner(): Indieinabox\SiteBuilder\ContentScanner`

Retrieves the content scanner instance.

@return \Indieinabox\SiteBuilder\ContentScanner

### getAssetPublisher()
`public function getAssetPublisher(): Indieinabox\SiteBuilder\AssetPublisher`

Retrieves the asset publisher instance.

@return \Indieinabox\SiteBuilder\AssetPublisher

### getFeedPublisher()
`public function getFeedPublisher(): Indieinabox\SiteBuilder\FeedPublisher`

Retrieves the feed publisher instance.

@return \Indieinabox\SiteBuilder\FeedPublisher

### getPagePublisher()
`public function getPagePublisher(): Indieinabox\SiteBuilder\PagePublisher`

Retrieves the page publisher instance.

@return \Indieinabox\SiteBuilder\PagePublisher

### getTranslationVirtualizer()
`public function getTranslationVirtualizer(): Indieinabox\SiteBuilder\TranslationVirtualizer`

Retrieves the translation virtualizer instance.

@return \Indieinabox\SiteBuilder\TranslationVirtualizer

### getIndexPublisher()
`public function getIndexPublisher(): Indieinabox\SiteBuilder\IndexPublisher`

Retrieves the index publisher instance.

@return \Indieinabox\SiteBuilder\IndexPublisher

### getTaxonomyService()
`public function getTaxonomyService(): Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface`

Retrieves the taxonomy service instance.

@return \Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface

### addManifest()
`public static function addManifest(string $path): void`

Adds a file path to the manifest array.

@param string $path
@return void

### build()
`public function build(): void`

Executes the main build pipeline.

Cleans the output directory, scans content files, handles translation virtualization,
and triggers generation of HTML, feeds, and static assets.

### scan()
`public function scan(string $dir): void`

Recursively scans a directory for markdown content files.
Delegates to ContentScanner.

@param string $dir The directory path to scan.
@return void

### generateHTMLFiles()
`public function generateHTMLFiles(): void`

Iterates over all parsed pages and triggers the generation of HTML,
Gemini, and Gopher files for each. Also generates sitemaps and indexes.

@return void

### ensureMandatoryHomepage()
`public function ensureMandatoryHomepage(): void`

Ensures a mandatory homepage (index.html) exists in the output.
Delegates to ContentScanner.

@return void
