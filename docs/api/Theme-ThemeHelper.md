# ThemeHelper
**Namespace:** `Indieinabox\Theme`

Class ThemeHelper
Provides autonomous methods for themes to retrieve standard markup for pages,
abstracting away the low-level logic of metadata, interactions, syndication, etc.

## Methods

### getMetadataHtml()
`public static function getMetadataHtml(Indieinabox\Page\Page $page): string`

Renders the post metadata HTML (date, tags, shortlinks, interactions summary).

### getIndieWebContext()
`public static function getIndieWebContext(Indieinabox\Page\Page $page): string`

Renders Indieweb properties context (e.g. in-reply-to, like-of, rsvp)

### getAITranslationNotice()
`public static function getAITranslationNotice(Indieinabox\Page\Page $page): string`

Renders AI Translation Notice if present

### getSyndicationLinks()
`public static function getSyndicationLinks(Indieinabox\Page\Page $page): string`

Renders Syndication Links (Also on...)

### getInteractionsHtml()
`public static function getInteractionsHtml(Indieinabox\Page\Page $page): string`

Renders Interactions (Likes, Reposts, Replies)

### renderPostSnippet()
`public static function renderPostSnippet(Indieinabox\Page\Page $contextPage, Indieinabox\Page\Page $post): string`

Renders a post snippet for list views (like indices and taxonomies).

@param Page $contextPage The page where this list is being rendered (for relative paths).
@param Page $post The post being rendered.
@return string
