# LanguageProcessor
**Namespace:** `Indieinabox\Markdown`

Class LanguageProcessor
Processes language-related data for a given page.

## Properties

### `private mixed $site`

@var Site

### `private mixed $urlTranslations`

@var UrlTranslations

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, Indieinabox\Translations\UrlTranslations $urlTranslations)`

LanguageProcessor constructor.

@param Site $site
@param UrlTranslations $urlTranslations

### processLanguage()
`public function processLanguage(Indieinabox\Page $page): Indieinabox\Page`

Process the language-related data for a given page.

@param Page $page
@return Page

### setDefaultLanguage()
`private function setDefaultLanguage(Indieinabox\Page $page): Indieinabox\Page`

Set the default language for the page.

@param Page $page
@return Page

### processOtherLanguages()
`private function processOtherLanguages(Indieinabox\Page $page): Indieinabox\Page`

Process other languages for the page.

@param Page $page
@return Page

### processLanguagePaths()
`private function processLanguagePaths(Indieinabox\Page $page): Indieinabox\Page`

Process language paths for the page.

@param Page $page
@return Page

### processOriginalContent()
`private function processOriginalContent(Indieinabox\Page $page): Indieinabox\Page`

Process original content for the page.

@param Page $page
@return Page

### determineOriginalContent()
`private function determineOriginalContent(Indieinabox\Page $page): string`

Determine the original content for the page.

@param Page $page
@return string

### generateLanguageSlugs()
`private function generateLanguageSlugs(Indieinabox\Page $page): array`

Generate language slugs for the page.

@param Page $page
@return array<int, string>
