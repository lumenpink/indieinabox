# SiteBuilder
**Namespace:** `Indieinabox`

Class SiteBuilder

Orchestrates the static site generation process. It coordinates content scanning,
translation virtualization, markdown body rendering, page publishing across multiple protocols
(HTML, Gemini, Gopher, ActivityPub), feed generation (RSS, Atom, Twtxt), taxonomy index publishing,
and static asset deployment.

## Architecture

`SiteBuilder` follows the Single Responsibility Principle as a high-level pipeline orchestrator.
Individual responsibilities are delegated to dedicated services injected via the constructor:

- **`ContentScanner`**: Scans markdown source files, handles fallback homepages, and renders raw bodies.
- **`TranslationVirtualizer`**: Ensures multilingual parity and virtualizes missing pages with pseudo-translations.
- **`PagePublisher`**: Publishes individual page documents (HTML, Gemini, Gopher, ActivityPub JSON).
- **`IndexPublisher`**: Compiles kind timelines, sitemaps, category/flowerbed taxonomies, and theme feed views.
- **`FeedPublisher`**: Generates RSS, Atom, and Twtxt syndicated feeds using pluggable feed generators.
- **`AssetPublisher`**: Manages static files, theme view assets, media copying, and garbage collection.

## Properties

### `private Indieinabox\Site $site`
Site configuration and environment settings.

### `private Indieinabox\Pages $pages`
Collection of processed pages.

### `private Indieinabox\ParserInterface $parser`
Markdown parser implementation.

### `private Indieinabox\SiteBuilder\ContentScanner $contentScanner`
Service for scanning content and rendering raw bodies.

### `private Indieinabox\SiteBuilder\AssetPublisher $assetPublisher`
Service for static files, theme assets, and media publishing.

### `private Indieinabox\SiteBuilder\FeedPublisher $feedPublisher`
Service for feed generation.

### `private Indieinabox\SiteBuilder\PagePublisher $pagePublisher`
Service for rendering page documents.

### `private Indieinabox\SiteBuilder\TranslationVirtualizer $translationVirtualizer`
Service for translation virtualization and parity.

### `private Indieinabox\SiteBuilder\IndexPublisher $indexPublisher`
Service for sitemaps, section indexes, and taxonomies.

### `public static array $manifest`
Registry of absolute file paths generated during the build, used for Garbage Collection.

## Methods

### __construct()
```php
public function __construct(
    Site $site,
    ?Pages $pages = null,
    ?ParserInterface $parser = null,
    ?AssetPublisher $assetPublisher = null,
    ?FeedPublisher $feedPublisher = null,
    ?PagePublisher $pagePublisher = null,
    ?TranslationVirtualizer $translationVirtualizer = null,
    ?IndexPublisher $indexPublisher = null,
    ?ContentScanner $contentScanner = null
)
```
Initializes the SiteBuilder orchestrator with optional custom service implementations.

### build()
```php
public function build(): void
```
Executes the complete build pipeline:
1. Scans content directory via `ContentScanner`.
2. Ensures mandatory homepage fallback via `ContentScanner`.
3. Enforces translation parity and virtualizes missing languages via `TranslationVirtualizer`.
4. Renders raw markdown bodies into HTML via `ContentScanner`.
5. Publishes pages across formats (HTML, Gemini, Gopher, ActivityPub) via `PagePublisher`.
6. Publishes feeds (RSS, Atom, Twtxt) via `FeedPublisher`.
7. Publishes section indexes, taxonomy pages, and sitemaps via `IndexPublisher`.
8. Copies static files and theme assets via `AssetPublisher`.
9. Executes garbage collection via `AssetPublisher`.

### getPages()
```php
public function getPages(): Indieinabox\Pages
```
Returns the processed collection of pages.

### getParser()
```php
public function getParser(): Indieinabox\ParserInterface
```
Returns the parser instance.

### getContentScanner()
```php
public function getContentScanner(): Indieinabox\SiteBuilder\ContentScanner
```

### getAssetPublisher()
```php
public function getAssetPublisher(): Indieinabox\SiteBuilder\AssetPublisher
```

### getFeedPublisher()
```php
public function getFeedPublisher(): Indieinabox\SiteBuilder\FeedPublisher
```

### getPagePublisher()
```php
public function getPagePublisher(): Indieinabox\SiteBuilder\PagePublisher
```

### getTranslationVirtualizer()
```php
public function getTranslationVirtualizer(): Indieinabox\SiteBuilder\TranslationVirtualizer
```

### getIndexPublisher()
```php
public function getIndexPublisher(): Indieinabox\SiteBuilder\IndexPublisher
```

### addManifest()
```php
public static function addManifest(string $path): void
```
Registers an absolute path into `$manifest` to prevent it from being removed during garbage collection.
