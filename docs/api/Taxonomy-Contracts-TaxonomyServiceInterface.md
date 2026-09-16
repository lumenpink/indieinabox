# TaxonomyServiceInterface
**Namespace:** `Indieinabox\Taxonomy\Contracts`

Interface TaxonomyServiceInterface

Defines contract for managing post kind taxonomy, kind configuration,
URL mapping, and slug translations.

## Methods

### getKindConfig()
`abstract public function getKindConfig(string $kind): array`

Retrieves kind configuration with sensible defaults.

@param string $kind
@return array<string, mixed>

### resolveKind()
`abstract public function resolveKind(?mixed $page): array`

Determines the kind and localized folder for a page.

@param Page|array<string, mixed> $page
@return array{localized: string, kind: string}

### getKindFolder()
`abstract public function getKindFolder(string $kind, string $lang): string`

Get the localized folder name for a specific kind and language.

@param string $kind
@param string $lang
@return string

### getKindLabel()
`abstract public function getKindLabel(string $kind, ?string $lang = null): string`

Return a human-readable, localized display label for a post kind.

@param string $kind
@param string|null $lang
@return string

### getKindLink()
`abstract public function getKindLink(Indieinabox\Page\Page $page, string $kind): string`

Return a hyperlinked, human-readable display label for a post kind.

@param Page $page
@param string $kind
@return string

### getOriginalContent()
`abstract public function getOriginalContent(string $slug, string $lang): string`

Get original content slug translation.

@param string $slug
@param string $lang
@return string

### isListingEligible()
`abstract public function isListingEligible(?mixed $var): bool`

Check if a page or kind is eligible to be shown on home/recent listings.

@param mixed $var
@return bool
