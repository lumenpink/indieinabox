# IndexPublisher
**Namespace:** `Indieinabox\SiteBuilder`

Publishes index pages: section indexes, timeline indexes, taxonomies, and sitemaps.

## Properties

### `private Indieinabox\Site\Site $site`

### `private Indieinabox\SiteBuilder\PagePublisher $pagePublisher`

### `private Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface $taxonomyService`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, Indieinabox\SiteBuilder\PagePublisher $pagePublisher, ?Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface $taxonomyService = null)`

### publishAll()
`public function publishAll(Indieinabox\Page\Pages $pages): void`

Publishes all aggregators, taxonomy indexes, sitemaps, and timeline pages.

@param Pages $pages
@return void

### publishSitemaps()
`public function publishSitemaps(): void`

Generates a sitemap.xml / index page for all active languages.

@return void

### publishKindIndexes()
`public function publishKindIndexes(Indieinabox\Page\Pages $pages): void`

Publishes section and timeline indexes for all configured post kinds.

@param Pages $pages
@return void

### publishTaxonomies()
`public function publishTaxonomies(Indieinabox\Page\Pages $pages): void`

Publishes index pages for standard taxonomies (tags and flowerbeds).

@param Pages $pages
@return void

### publishTimelineStaticPage()
`public function publishTimelineStaticPage(): void`

Compiles the static timeline page from subscribed feeds and hubs.

@return void

### loadThemeFeedView()
`public function loadThemeFeedView(Indieinabox\Page\Pages $pages): void`

Loads the theme feed view file if provided by the active theme.

@param Pages $pages
@return void

### compileTimelineIndexes()
`public function compileTimelineIndexes(string $targetKind, array $pages): void`

@param string $targetKind
@param Page[] $pages
@return void

### compileSectionIndexes()
`public function compileSectionIndexes(string $targetKind, array $pages): void`

@param string $targetKind
@param array<int, Page> $pages
@return void

### compileTaxonomyIndexes()
`public function compileTaxonomyIndexes(string $taxonomyName, string $taxonomyKey, iterable $pages): void`

Compiles index pages for a taxonomy (e.g. tags or flowerbeds).

@param string $taxonomyName The internal slug (e.g. 'tag', 'flowerbed')
@param string $taxonomyKey The metadata key (e.g. 'tags', 'flowerbed')
@param iterable<Page> $pages
@return void

### getTaxonomyService()
`public function getTaxonomyService(): Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface`

Retrieves the taxonomy service instance.

@return \Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface
