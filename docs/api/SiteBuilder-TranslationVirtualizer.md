# TranslationVirtualizer
**Namespace:** `Indieinabox\SiteBuilder`

Handles translation parity and virtualization of missing pages across languages.

## Properties

### `private Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### virtualize()
`public function virtualize(Indieinabox\Pages $pages): void`

Generates pseudo-translated pages for missing languages to maintain parity.
Uses configured rules (e.g., full parity, from-main-only) and translates
missing slugs according to URL translation mappings.

@param Pages $pages
@return void

### pseudoTranslate()
`public function pseudoTranslate(Indieinabox\Page $page, string $targetLang): void`

Applies a pseudo-translation prefix to a page's title or content.
Used visually to flag that a page was automatically virtualized.

@param Page $page The page to translate in place.
@param string $targetLang The target language code used as the prefix.
@return void
