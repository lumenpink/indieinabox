# TaxonomyService
**Namespace:** `Indieinabox\Taxonomy`

Class TaxonomyService

Domain service managing post kind taxonomy, slug-to-kind mapping,
localized folder resolution, and menu links.

## Properties

### `private ?Indieinabox\Site\Site $site`

### `private ?Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settingsRepo`

## Methods

### __construct()
`public function __construct(?Indieinabox\Site\Site $site = null, ?Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settingsRepo = null)`

### getSite()
`private function getSite(): ?Indieinabox\Site\Site`

### getKindConfig()
`public function getKindConfig(string $kind): array`

Retrieves kind configuration with sensible defaults.

@param string $kind
@return array<string, mixed>

### resolveKind()
`public function resolveKind(?mixed $page): array`

Determines the kind and localized folder for a page.

@param Page|array<string, mixed> $page
@return array{localized: string, kind: string}

### getKindFolder()
`public function getKindFolder(string $kind, string $lang): string`

Get the localized folder name for a specific kind and language.

@param string $kind
@param string $lang
@return string

### getKindLabel()
`public function getKindLabel(string $kind, ?string $lang = null): string`

Return a human-readable, localized display label for a post kind.

@param string $kind Internal kind slug
@param string|null $lang Target language (defaults to current page lang)
@return string

### getKindLink()
`public function getKindLink(Indieinabox\Page\Page $page, string $kind): string`

Return a hyperlinked, human-readable display label for a post kind.

@param Page $page The page context where this link is rendered
@param string $kind Internal kind slug
@return string

### getOriginalContent()
`public function getOriginalContent(string $slug, string $lang): string`

Get original content slug translation.

@param string $slug
@param string $lang
@return string

### isListingEligible()
`public function isListingEligible(?mixed $var): bool`

Check if a page or kind is eligible to be shown on home/recent listings.

@param mixed $var
@return bool
