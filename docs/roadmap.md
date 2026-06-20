# Project Roadmap & Refactoring History

Indieinabox is transitioning from a legacy procedural model (based on global functions and associative arrays) to a structured, typed object-oriented model (OOP classes with namespaces under the `Indieinabox` root).

This document tracks completed refactoring phases and future directions.

---

## Completed Milestones

### 🏗️ Phase 1: Bootstrap & Config Realignment (June 2026)
*   **Bootstrap Repair**: Corrected autoloader require path in `build.php` from `autoloader.php` to `autoload.php` and defined global `DS` directory separator.
*   **Config mappings alignment**: Upgraded config parser loop in `build.php` to map lowercase config keys from `config.yml` into camelCase properties of typed configuration classes (`Site\Paths`, `Site\Options`, `Site\Localization`).
*   **Collection iteration fixes**: Adjusted `Pages` associative class to populate parent `ArrayObject` offset tables, ensuring clean counting and iteration.
*   **Legacy Compatibility Bridge**: Implemented `ArrayAccess` on `Page` as a temporary bridge allowing templates and procedural scripts to work with bracket syntax (e.g. `$page["lang"]`).

### ⚡ Phase 2: OOP Shortcut Properties & Template Migration (June 2026)
*   **OOP Page shortcuts**: Implemented magic getter/setter/isset methods in `Page.php` forwarding flat property queries (such as `$page->lang` or `$page->title`) to nested composed child objects (`Page\Localization`, `Page\Metadata`, `Page\Content`), avoiding 3-level deep nested accesses in templates.
*   **String casting on Content**: Added `__toString()` on `Page\Content` class to allow clean template casting (`<?= $page->content ?>`) without warnings.
*   **FileProcessor refactoring**: Updated namespaced `FileProcessor` support list checks to match `Support` object configuration.
*   **Templates & Helpers migration**: Rewrote all templates under `_template/` and helper functions under `_engine/functions/` to use OOP arrow syntax.
*   **ArrayAccess removal**: Cleanly removed `ArrayAccess` interface and implementations from `Page.php`.
*   **IDE Static analysis cleanup**: Prepended PHPDoc variable annotations to templates, resolving all "Undefined variable" IDE warnings.

---

### 🏗️ Phase 3: Directory Structure Refactoring (June 2026)
*   **PSR-4 Autoloading**: Configured Composer to autoload classes under the `Indieinabox\` namespace directly from the `app/` folder.
*   **Unified Bootstrap**: Created `bootstrap/app.php` to initialize autoloader and procedural helpers/data files, replacing custom loaders.
*   **Standardized Paths**: Realigned target workspaces to modern conventions (`public/`, `content/`, `data/`, `resources/views/`, `resources/static/`).
*   **Root Build Runner**: Migrated the main site compilation script to a root-level `build.php` executing the generation pipeline.
*   **Documentation Refactoring**: Cleaned the main `README.md` and updated all documentation under `docs/` to reflect the new structure.

### ⚙️ Phase 4: Full Procedural Helpers Migration (June 2026)
*   **Namespaced Helpers**: Migrated procedural functions inside `app/functions/` to namespaced classes like `Helper` and static helper methods.
*   **Unified Global Wrappers**: Replaced scattered procedural files in `app/functions/` with a single `helpers.php` wrapper file for backward-compatibility with template variables.
*   **Structured SiteBuilder**: Migrated the build pipeline execution and static copying logic from procedural functions inside `build.php` to the new `SiteBuilder` class.

### 🔍 Phase 5: Parser Transition (June 2026)
*   **MarkdownParser Integration**: Swapped out the legacy procedural `parse()` bridge function (previously in `app/functions/parse.php`) for the direct object-oriented usage of `MarkdownParser` in the `SiteBuilder` scanning pipeline and functional tests.
*   **Modular Processors**: Cleanly enabled the pipeline to instantiate and call modular namespaced processor classes (`FileProcessor`, `ContentProcessor`, `LanguageProcessor`).

### 🌐 Phase 6: Web / CLI Single-File Entry & Webmentions (June 2026)
*   **Web SAPI Routing**: Implemented conditional execution inside `build.php` to handle CLI static page compilation and Web request routing separately based on `php_sapi_name()`.
*   **WebRouter Dev Server**: Created `WebRouter` to route requests, serving static files directly from the output directory `public/` and handling webmention endpoints.
*   **Webmention Verification Endpoint**: Implemented `WebmentionHandler` to validate and process incoming webmentions via beauty URLs (e.g. `/webmention`) and query parameters (e.g. `?webmention`).
*   **Source Linking Validation**: Enabled automatic fetching and parsing of external source pages to verify presence of absolute or relative back-links to target pages.
*   **Aggregated Webmention Storage**: Configured webmentions to be saved under `data/webmentions/<md5_slug>.json` while filtering out duplicate sources.
*   **Premium Presentation Layer**: Created an aesthetically rich, responsive HTML/CSS Webmention helper form using CSS backdrop filters, glassmorphism, and HSL tailored dark-mode colors.

---

### 🔑 Phase 7: Simple IndieAuth Endpoint (June 2026)
*   **Hidden Configuration Priority**: Updated configuration loader in `build.php` to prioritize loading settings from `.config.yml` if it exists, securing secrets like passwords in production.
*   **Metadata Endpoint Discovery**: Implemented compliant OAuth 2.0 authorization server metadata discovery served dynamically from the site FQDN.
*   **Authorization Code & PKCE Validation**: Developed a stateless authorization flow supporting PKCE `S256` and `plain` code challenges and verification.
*   **Token Issue & Bearer Verification**: Developed token exchange capabilities and bearer token validation via HTTP `Authorization` headers.
*   **Premium Presentation Layer**: Created an aesthetically rich, responsive login layout utilizing Google Fonts, backdrop blur filters, and smooth CSS animations.

### 🛠️ Phase 8: Custom AST-based Markdown Parser (June 2026)
*   **Recreation of Parser**: Replaced the legacy Parsedown library with a lightweight, clean-room custom Markdown parser design (`ASTParser`).
*   **Abstract Syntax Tree (AST)**: Implemented a two-pass parser architecture (block parsing and inline parsing) that constructs a structured Abstract Syntax Tree (AST) representing the document structure using strongly typed Node classes.
*   **Type Safety**: Created concrete namespaced OOP node classes (`RootNode`, `HeadingNode`, `ParagraphNode`, `ListNode`, `ListItemNode`, `InlineNode`, `TextNode`, `StrongNode`, `EmphasisNode`, `CodeInlineNode`, `WikilinkNode`) to ensure type safety.
*   **Visitor-pattern HTML Renderer**: Created `HtmlRenderer` to walk the AST nodes and output clean semantic HTML markup.

---

### 📤 Phase 9: AST-driven Multi-Format Output Engine (June 2026)
*   **Flexible Rendering Engine**: Developed a modular renderer that consumes the custom Markdown AST to compile the site's content into multiple protocols/formats simultaneously:
    *   **HTML**: Render semantic, responsive HTML structures for traditional web browsers.
    *   **Gemini (gemproto)**: Generate lightweight line-oriented `.gmi` pages for the Gemini protocol.
    *   **Gopher (gophermap)**: Build structured `gophermap` selectors for retro Gopher protocol browsers.
*   **Static Exporter**: Integrated the output engine into `SiteBuilder` to compile the site for all three formats during the build pipeline.

### 📡 Phase 10: Twtxt Publishing & Consuming (June 2026)
*   **Twtxt Feed Generation**: Created a builder/renderer that automatically formats site posts/updates into a standard flat-text `twtxt.txt` feed (a simple `<timestamp>\t<text>` format) and publishes it at the root of the static site.
*   **Feed Aggregator & Consumer**: Built a local twtxt parser that fetches and reads remote twtxt feeds, parsing user mentions, hashtags, and timestamps for localized display.
*   **Hub Integration**: Integrated with federated twtxt hubs to search, query, and retrieve mentions, replies, and updates beyond the local subscription list, expanding the reach of the site's microblogging footprint.

### 🗄️ Phase 10.5: SQLite Database Migration (June 2026)
*   **Centralized Configuration:** Extinguished the legacy `data/` folder flat-files (`config.yml`, `chars.php`, `intl.php`, etc) migrating all application configurations, translations, and globalization mappings to a centralized `indieinabox.sqlite` database.
*   **Installation Interface:** Created a dynamic installer that generates the database schema automatically from `database.sql` if it detects a missing environment configuration.
*   **Single-File Payload:** Refactored `compile.php` to embed the database SQL and installer logic directly into the generated `indieinabox.php` package, keeping it completely self-contained.
*   **PDO Driver Upgrade:** Migrated from native `SQLite3` class to the universally available `PDO_SQLite` driver to bulletproof compatibility across custom hosting environments and containerized distributions.

### ✍️ Phase 11: Micropub API Support (COMPLETED)
- [x] Create the W3C Micropub Endpoint (`/micropub`).
  - [x] Support `q=config` and `q=syndicate-to`.
  - [x] Support `POST` creation requests with frontmatter/YAML.
  - [x] Handle standard (`h-entry`, `content`, `category`, `name`, `mp-slug`).
  - [x] Build custom integration for `mp-language`.
- [x] Create Media Endpoint (`/micropub/media`) with collision detection.
- [x] Add `<link rel="micropub">` and Webmention/Microsub links pointing to local/hosted services.
- [x] Build a local web-based client at `/micropub/client` to allow native dashboard posting! HTTP requests via Bearer access tokens, enforcing scopes like `create`, `update`, `delete`, and `media`.
- [x] **Advanced Client UI**: Implemented dynamic toggles for Post Types (Article, Note, Photo) and gallery reordering for multiple media uploads.
- [x] **Dev Server Media Fallback**: Adjusted static routing to serve pre-build media uploads directly from `content/media` to prevent 404s in the client interface.

---

## Future Roadmap

The following next-generation features are scheduled for development:

### 🎨 Phase 11.5: Whostyle JSON Integration
*   **Decentralized Styling**: Integrate the newly updated Whostyle JSON (v1.1) specification into the Indieinabox ecosystem.
*   **Webmention Rendering**: Apply safely-clamped CSS design tokens to incoming third-party comments and webmentions to preserve the author's visual identity.
*   **Inline & External Discovery**: Implement both inline `<script type="application/whostyle+json">` extraction and external `<link rel="whostyle">` fetching mechanisms within the webmention processing pipeline.

### 📬 Phase 12: Microsub Endpoint & Reader (with Twtxt)
*   **Microsub Server**: Implement a W3C Microsub endpoint to manage feeds, channels (e.g., Inbox, Friends, Tech), and read states (read, unread, archived).
*   **Twtxt Feeds Bridging**: Enable subscription to standard `twtxt.txt` feeds alongside traditional RSS, Atom, and Microformats-parsed feeds, converting microblog posts into unified Microsub timeline entries.
*   **Token Verification**: Verify bearer tokens using the local IndieAuth endpoint to authorize feed readers/clients.

### 🌐 Phase 13: ActivityPub Federated Protocol (Publishing & Reading)
*   **Actor Profiles & WebFinger**: Implement WebFinger query routing (`/.well-known/webfinger`) and JSON-LD ActivityPub Actor profiles so the site can be searched and followed on the Fediverse (e.g., Mastodon).
*   **Inbox & Outbox Handling**: Create an ActivityPub Inbox/Outbox system supporting HTTP Signatures verification.
*   **Publishing & Reading**: Publish new site posts automatically to followers' inboxes, and utilize the local Microsub endpoint as a centralized hub to fetch, store, and display incoming feed items from the Fediverse.


