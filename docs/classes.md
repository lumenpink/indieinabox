# Core Namespaced Classes

This document describes the primary object-oriented classes under the `Indieinabox` namespace, their roles, and how they compose the generator architecture.

---

## 🌟 Universal Entry (`Indieinabox\Entry\Entry`)

The `Entry` class is the central domain entity unifying content representation across the entire system. It acts as the common lingua franca for:
- Internal site pages.
- Syndicated feeds (RSS, Atom, Twtxt).
- Federated social feeds and notifications (ActivityPub, Microsub).

### Properties & Features:
- Standardized fields: `$id`, `$url`, `$title`, `$content`, `$published`, `$updated`, `$authorName`, `$authorUrl`, `$authorAvatar`, `$summary`, `$tags`, `$media`, `$extra`.
- Federation extensions: `$contentWarning` and `$poll`.
- Factory methods: `fromPage(Page $page, Site $site)` and `fromTwtxt(string $date, string $text, ...)`.
- Magic getters (`__get`, `__isset`) for backwards compatibility.

---

## 🏗️ SiteBuilder Orchestration Suite (`Indieinabox\SiteBuilder`)

The site build pipeline is composed of focused single-responsibility services coordinated by `SiteBuilder`:

### 1. `SiteBuilder` (Orchestrator)
Coordinates the execution flow of the build pipeline via high-level Dependency Injection:
- Scanning ➔ Virtualization ➔ Body Rendering ➔ Publishing (Pages, Indexes, Feeds) ➔ Asset Copying & Garbage Collection.

### 2. `SiteBuilder\ContentScanner`
- Recursively scans content directories for Markdown files.
- Initializes Markdown parsing processors.
- Ensures mandatory homepage fallback (`/`).
- Renders raw Markdown bodies into final HTML (`renderRawBodies`).

### 3. `SiteBuilder\TranslationVirtualizer`
- Enforces multilingual parity rules (`translation_parity_rule`).
- Clones and pseudo-translates missing pages (`pseudoTranslate`).

### 4. `SiteBuilder\PagePublisher`
- Publishes individual pages to multiple protocols: HTML, Gemini (`.gmi`), Gopher (`gophermap`), and ActivityPub JSON.
- Computes language switchers and menu links.

### 5. `SiteBuilder\IndexPublisher`
- Compiles XML/HTML sitemaps per active language.
- Generates section and reverse-chronological monthly timeline archives.
- Compiles taxonomy index pages for tags (`/tag/`) and flowerbeds (`/flowerbed/`).
- Compiles static timeline pages from themes (`timeline/index.html`).

### 6. `SiteBuilder\FeedPublisher`
- Publishes RSS 2.0, Atom 1.0, and Twtxt syndicated feeds using pluggable generators consuming `Entry` domain entities.

### 7. `SiteBuilder\AssetPublisher`
- Copies theme static assets, CSS/JS views assets, and media directories.
- Manages the build manifest (`SiteBuilder::$manifest`) and executes garbage collection to remove obsolete files.

---

## 📄 Page (`Indieinabox\Page`)

The `Page` class represents a parsed input source file (Markdown, text). It is a composite object that bundles page parameters into smaller typed sub-components:

### Composed Sub-components:
1. **`Page\Metadata`**: Document properties parsed from frontmatter:
   - `$title` (string)
   - `$tags` (array of strings)
   - `$category` (array of strings)
   - `$nick` (string)
   - `$noauthor` (bool)
   - `$kind` (string, e.g. "note", "photo", "reply")
   - `$layout` (string, e.g. "page", "home")
2. **`Page\Content`**: Document body content and assets:
   - `$content` (string, HTML rendered version)
   - `$originalcontent` (string, raw body path)
   - `$images` (array of images info)
3. **`Page\Localization`**: Translation configuration:
   - `$lang` (string, e.g. "en", "pt-br")
   - `$langpath` (string, e.g. "en/")
   - `$langslug` (array|string)
   - `$otherlang` (array of alternative locales)
   - `$otherlangpath` (array of alternative locale paths)
   - `$localizeddate` (string)
   - `$localizedkind` (string)

---

## 🌐 Site (`Indieinabox\Site`)

The `Site` class serves as the root configuration settings block loaded from `config.yml`. It aggregates config namespaces:

* **`Site\Metadata`**: High-level details (`$title`, `$sitename`, `$author`, `$defaultTitle`, `$fqdn`).
* **`Site\Paths`**: Workspace directories (`$baseDir`, `$outputDirHtml`, `$contentDir`, `$themeDir`).  
* **`Site\Options`**: Generation options (`$buildAll`, `$dev`, `$skipStatic`, `$forceStaticOverride`, `$htmlpostprocessing`).
* **`Site\Localization`**: Locales settings (`$lang` array, `$defaultLang`).
* **`Site\Support`**: Valid extensions list (`$support` array, `$defaultCategory`).

---

## 📚 Pages Collection (`Indieinabox\Pages`)

Extends `ArrayObject` to hold lists of `Page` objects.
* **`add(Page|array $page, ?string $id)`**: Appends a page to the collection using its slug as the key.
* **`all()`**: Returns the raw array map of slug -> Page objects.
* **`get(string $id)`**: Retrieves a Page object by its slug key.

---

## 🎨 Theme Manager (`Indieinabox\ThemeManager`)

Manages view template inclusion, partial resolution, and template rendering:
- **`loadView(string $path, array $data)`**: Loads and evaluates a view from disk or embedded `DefaultTheme`.
- **`renderView(string $path, array $data)`**: Captures buffered output of a view as string.
- **`includeView(string $relPath, array $data)`**: Resolves partial views (e.g. `includes/head.php`).
- **`hasView(string $path)`**: Checks whether a view exists on disk or in `DefaultTheme`.

---

## 📡 Pluggable Feeds (`Indieinabox\Feeds`)

- **`FeedGeneratorInterface`**: Standard contract for feed generation.
- **`Generators\RssFeedGenerator`**: RSS 2.0 XML feeds.
- **`Generators\AtomFeedGenerator`**: Atom 1.0 RFC 4287 feeds.
- **`Generators\TwtxtFeedGenerator`**: Twtxt microblog feeds.

---

## 🚀 Bootstrap (`Indieinabox\Bootstrap`)

Bootstraps runtime dependencies, configuration tables, global helpers, and environmental constants.

---

## 🔍 Parser & Processors

### `Indieinabox\MarkdownParser`
The main parser class that orchestrates scanning and splitting source files. Delegates to:
* **`Markdown\FileProcessor`**: Validates extensions and resolves layout templates.
* **`Markdown\ContentProcessor`**: Extracts frontmatter and converts markdown.
* **`Markdown\LanguageProcessor`**: Determines active page language and translated paths.

### `Indieinabox\Markdown\ASTParser` & `Indieinabox\Markdown\HtmlRenderer`
Lightweight custom Markdown AST parser and semantic HTML renderer.

---

## 🔀 Web Router (`Indieinabox\WebRouter`)
Orchestrates HTTP requests under Web SAPIs, mapping URIs to dedicated handlers and serving static assets.

## 🗄️ Archive Handler (`Indieinabox\ArchiveHandler`)
Serves local link snapshots, external archive fallbacks, and processes force snapshot requests.

## 📩 Webmention Subsystem (`Indieinabox\Webmention`)

The Webmention subsystem handles incoming notifications and dispatches outgoing webmentions for linked external resources:

### 1. `WebmentionHandler` (`Indieinabox\WebmentionHandler`)
The HTTP endpoint orchestrator for `/webmention`:
- Validates source and target URLs.
- Ensures the target URL belongs to the local site and points to an existing published resource.
- Enqueues valid webmentions into `inbox_queue` (HTTP 202 Accepted).
- Serves the endpoint test/help interface on GET requests.

### 2. `WebmentionSender` (`Indieinabox\WebmentionSender`)
Dispatches outgoing webmentions:
- Scans newly published or updated content and frontmatter for outbound external links.
- Uses `LinkExtractor` to extract, deduplicate, and filter self-pings.
- Enqueues targets into `outgoing_webmentions` table.

### 3. `Webmention\SourceVerifier` (`Indieinabox\Webmention\SourceVerifier`)
Verifies reciprocal backlinks and extracts remote metadata:
- Resolves relative URLs (`/path`, `../path`, `dir/path`) against the source page origin.
- Normalizes URLs (protocol, case-insensitivity, trailing slashes).
- Extracts author info, `<title>`, `e-content` microformats, and Whostyles V2 hashes.

### 4. `Webmention\LinkExtractor` (`Indieinabox\Webmention\LinkExtractor`)
Parses and filters target URLs:
- Extracts URLs from interaction frontmatter properties (`in-reply-to`, `like-of`, `repost-of`, `bookmark-of`).
- Extracts URLs from Markdown links `[text](url)`, HTML `<a href="...">` tags, and bare URLs.
- Automatically filters out self-pings matching the source origin.

### 5. `Webmention\HelpPageView` (`Indieinabox\Webmention\HelpPageView`)
Renders the standalone, responsive HTML help and test-form interface for GET requests.

## 🔑 IndieAuth Handler (`Indieinabox\IndieAuthHandler`)
Provides IndieAuth / OAuth 2.0 PKCE authentication server endpoints.


---

## 🌐 ActivityPub Subsystem (`Indieinabox\ActivityPub`)

The ActivityPub federation architecture is decoupled into focused services:

### 1. `ActivityPubHandler` (`Indieinabox\ActivityPubHandler`)
The primary HTTP orchestrator for Fediverse endpoints:
- `handleWebFinger()`: Responds to `/.well-known/webfinger` queries with JRD JSON.
- `handleActor()`: Serves `/actor` profile JSON-LD.
- `handleInbox()`: Ingests incoming activities into `inbox_queue` (HTTP 202 Accepted).
- `handleOutbox()`: Serves `/outbox` ordered collection.
- `queueCreateActivity()` & `queueAcceptFollow()`: Outbox queuing and broadcasting to followers.
- `handleInteract()` & `handleAuthorizeInteraction()`: Delegated to `InteractionHandler`.

### 2. `ActivityPub\ActivityBuilder` (`Indieinabox\ActivityPub\ActivityBuilder`)
Constructs valid ActivityStreams 2.0 objects and activities:
- `buildObjectForPageArray()`: Maps local notes/articles to ActivityStreams representations, including attachments, local emoji shortcodes, BookWyrm ratings/reviews, and syndication actors.
- `buildCreateActivity()`, `buildAcceptActivity()`, `buildInteractionActivity()`: Activity envelope builders.

### 3. `ActivityPub\KeyManager` (`Indieinabox\ActivityPub\KeyManager`)
Manages cryptographic keys for Fediverse HTTP signatures:
- `ensureKeys()`: Generates 2048-bit RSA key pairs when missing.
- `getPublicKey()`, `getPrivateKey()`: Retrieves PEM strings from the database.

### 4. `ActivityPub\InteractionHandler` (`Indieinabox\ActivityPub\InteractionHandler`)
Client-side Fediverse interactions:
- Renders interaction interfaces for `/interact` and `/authorize_interaction`.
- Dispatches Likes, Reposts (Announce), and Replies to remote actors.
- Optionally creates local markdown posts for public syndication.

