# SeoMetadataResolver
**Namespace:** `Indieinabox\Taxonomy`

Class SeoMetadataResolver

Domain service responsible for extracting, resolving, and formatting SEO descriptions,
fallback images, absolute URLs, and Schema.org structured data types for pages.

## Properties

### `private ?Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(?Indieinabox\Site\Site $site = null)`

### getSite()
`private function getSite(): ?Indieinabox\Site\Site`

### resolve()
`public function resolve(Indieinabox\Page\Page $page): array`

Extract and normalize SEO metadata for a page.

@param Page $page
@return array{description: string, image: string, image_alt: string, schema_type: string}
