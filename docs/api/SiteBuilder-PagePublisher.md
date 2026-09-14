# PagePublisher
**Namespace:** `Indieinabox\SiteBuilder`

Class PagePublisher

Responsible for publishing individual `Page` entities across multiple protocols and formats:
HTML, Gemini gemtext (`.gmi`), Gopher (`gophermap`), and ActivityPub JSON.

## Methods

### __construct()
```php
public function __construct(Site $site, Pages $pages)
```
Initializes the publisher with site settings and page collection.

### publish()
```php
public function publish(Page $page): void
```
Publishes a single page into all enabled formats (skips draft pages).

### publishAll()
```php
public function publishAll(): void
```
Iterates through all pages in the collection and calls `publish()` on each.

### publishHtml()
```php
public function publishHtml(Page $page): void
```
Renders HTML page representation using `ThemeManager`, calculates menu links and language switchers,
minifies or beautifies HTML, writes `index.html`, and writes ActivityPub JSON companion file.

### publishGemini()
```php
public function publishGemini(Page $page): void
```
Renders gemtext using `GemtextRenderer` and writes `index.gmi`.

### publishGopher()
```php
public function publishGopher(Page $page): void
```
Renders gophermap text using `GophermapRenderer` and writes `gophermap`.

### getLanguageLinks()
```php
public function getLanguageLinks(Page $page): array
```
Computes relative URLs linking to translated variants of the given page.

### getMenuLinks()
```php
public function getMenuLinks(string $currentLang): array
```
Computes navigation links divided into `header` and `footer` categories based on page metadata.
