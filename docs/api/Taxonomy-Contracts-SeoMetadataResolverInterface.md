# SeoMetadataResolverInterface
**Namespace:** `Indieinabox\Taxonomy\Contracts`

Interface SeoMetadataResolverInterface

Defines contract for extracting and resolving SEO metadata, social share images,
and Schema.org structured types for pages.

## Methods

### resolve()
`abstract public function resolve(Indieinabox\Page\Page $page): array`

Extract and normalize SEO metadata for a page.

@param Page $page
@return array{description: string, image: string, image_alt: string, schema_type: string}
