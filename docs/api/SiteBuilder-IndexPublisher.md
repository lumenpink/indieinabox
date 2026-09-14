# IndexPublisher
**Namespace:** `Indieinabox\SiteBuilder`

Class IndexPublisher

Compiles sitemaps, kind section indexes, timeline archive indexes, taxonomy listings
(tags and flowerbeds), timeline static pages, and theme feed views.

## Methods

### __construct()
```php
public function __construct(Site $site, PagePublisher $pagePublisher)
```
Initializes the index publisher with site configuration and page publisher.

### publishAll()
```php
public function publishAll(Pages $pages): void
```
Convenience method that triggers sitemaps, section/timeline indexes, and taxonomy indexes.

### publishSitemaps()
```php
public function publishSitemaps(): void
```
Generates root sitemaps (`/index/` and `{lang}/index/`) listing all site content per active language.

### publishSectionAndTimelineIndexes()
```php
public function publishSectionAndTimelineIndexes(Pages $pages): void
```
Generates indexes for each configured kind (e.g. `notes`, `articles`).
For kinds configured with `full_content`, compiles monthly archives (`notes/YYYY-MM/`)
and reverse chronological timeline indexes.

### publishTaxonomyIndexes()
```php
public function publishTaxonomyIndexes(Pages $pages): void
```
Compiles index pages for all tags (`/tag/{slug}/`) and digital garden flowerbeds (`/flowerbed/{slug}/`).

### publishTimelineStaticPage()
```php
public function publishTimelineStaticPage(): void
```
If the theme includes `views/timeline.php`, compiles `timeline/index.html` static view.

### loadThemeFeedView()
```php
public function loadThemeFeedView(Pages $pages): void
```
Loads custom feed views provided by themes (e.g. `views/feed.php`).
