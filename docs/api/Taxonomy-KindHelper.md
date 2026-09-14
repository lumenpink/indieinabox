# KindHelper
**Namespace:** `Indieinabox\Taxonomy`

Class KindHelper

Manages post kind taxonomy, kind configuration, URL mapping,
post listing, SEO metadata resolution, and incoming interactions.

## Methods

### getSite()
`private static function getSite(): ?Indieinabox\Site`

### getKindConfig()
`public static function getKindConfig(string $kind): array`

Retrieves kind configuration with sensible defaults.

@param string $kind
@return array<string, mixed>

### kind()
`public static function kind(?mixed $page, ?Indieinabox\Site $siteInstance = null): array`

Determines the kind and localized folder for a page.

@param Page|array<string, mixed> $page
@param Site|null $siteInstance
@return array{localized: string, kind: string}

### getKindFolder()
`public static function getKindFolder(string $kind, string $lang): string`

Get the localized folder name for a specific kind and language.

@param string $kind
@param string $lang
@return string

### kindLabel()
`public static function kindLabel(string $kind, ?string $lang = null): string`

Return a human-readable, localized display label for a post kind.

@param string $kind Internal kind slug
@param string|null $lang Target language (defaults to current page lang)
@return string

### kindLink()
`public static function kindLink(Indieinabox\Page $page, string $kind): string`

Return a hyperlinked, human-readable display label for a post kind.

@param Page $page The page context where this link is rendered
@param string $kind Internal kind slug
@return string

### getOriginalContent()
`public static function getOriginalContent(string $slug, string $lang): string`

Get original content slug translation.

@param string $slug
@param string $lang
@return string

### listposts()
`public static function listposts(): string`

List posts, sorting by date descending, up to 10 posts.

@return string

### removeGeneric()
`public static function removeGeneric(?mixed $var): bool`

Remove generic/page items from filter list.

@param mixed $var
@return bool

### getSeoMetadata()
`public static function getSeoMetadata(Indieinabox\Page $page): array`

Helper function to extract and normalize SEO metadata.

@param Page $page
@return array<string, string>

### getInteractions()
`public static function getInteractions(Indieinabox\Page $page, ?string $type = null): array`

Get incoming interactions (likes, reposts, replies) for a specific Page.

@param Page $page
@param string|null $type (e.g. 'like', 'repost', 'reply')
@return array<int, mixed>
