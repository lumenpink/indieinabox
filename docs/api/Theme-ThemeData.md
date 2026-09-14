# ThemeData
**Namespace:** `Indieinabox\Theme`

Class ThemeData

Provides autonomous methods to generate template data and markup for themes,
decoupling low-level logic from presentation views.

## Methods

### getPageTitle()
`public static function getPageTitle(Indieinabox\Page $page, Indieinabox\Site $site): string`

Gets the formatted page title.

@param Page $page
@param Site $site
@return string

### getThemeColors()
`public static function getThemeColors(Indieinabox\Page $page): array`

Gets the calculated theme colors based on the page kind.

@param Page $page
@return array<string, string> An array containing 'bg' and 'fg' color hex codes.

### getMetaTags()
`public static function getMetaTags(Indieinabox\Page $page, Indieinabox\Site $site): string`

Generates the standard meta tags (description, canonical, shortlink).

@param Page $page
@param Site $site
@return string HTML meta tags.

### getOpenGraphTags()
`public static function getOpenGraphTags(Indieinabox\Page $page, Indieinabox\Site $site): string`

Generates OpenGraph meta tags.

@param Page $page
@param Site $site
@return string HTML meta tags.

### getTwitterCardTags()
`public static function getTwitterCardTags(Indieinabox\Page $page, Indieinabox\Site $site): string`

Generates Twitter Card meta tags.

@param Page $page
@param Site $site
@return string HTML meta tags.

### getJsonLd()
`public static function getJsonLd(Indieinabox\Page $page, Indieinabox\Site $site): string`

Generates the Schema.org JSON-LD script tag.

@param Page $page
@param Site $site
@return string HTML script tag containing JSON-LD.

### getLanguageSelector()
`public static function getLanguageSelector(Indieinabox\Page $page, Indieinabox\Site $site, ?array $langLinks = null): string`

Generates the language selector HTML markup.

@param Page $page
@param Site $site
@param array<string, string>|null $langLinks Array of language codes to relative paths.
@return string HTML nav block or empty string if single language.

### getHeaderNavLinks()
`public static function getHeaderNavLinks(Indieinabox\Page $page, Indieinabox\Site $site, array $headerLinks = []): string`

Generates the top navigation links HTML markup.

@param Page $page
@param Site $site
@param array<int, array<string, string>> $headerLinks
@return string HTML nav block.

### getFooterLinks()
`public static function getFooterLinks(Indieinabox\Page $page, array $footerLinks = []): string`

Generates the footer links HTML markup including RSS and ATOM.

@param Page $page
@param array<int, array<string, string>> $footerLinks
@return string HTML nav block.
