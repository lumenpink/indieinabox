# Project Roadmap & Refactoring History

Indieinabox is transitioning from a legacy procedural model (based on global functions and associative arrays) to a structured, typed object-oriented model (OOP classes with namespaces under the `Indieinabox` root).

This document tracks completed refactoring phases and future directions.

---

## Completed Milestones

### 🏗️ Phase 1: Bootstrap & Config Realignment (June 2026)
- [x] **Bootstrap Repair**: Corrected autoloader require path in `build.php` from `autoloader.php` to `autoload.php` and defined global `DS` directory separator.
- [x] **Config mappings alignment**: Upgraded config parser loop in `build.php` to map lowercase config keys from `config.yml` into camelCase properties of typed configuration classes (`Site\Paths`, `Site\Options`, `Site\Localization`).
- [x] **Collection iteration fixes**: Adjusted `Pages` associative class to populate parent `ArrayObject` offset tables, ensuring clean counting and iteration.
- [x] **Legacy Compatibility Bridge**: Implemented `ArrayAccess` on `Page` as a temporary bridge allowing templates and procedural scripts to work with bracket syntax (e.g. `$page["lang"]`).

### ⚡ Phase 2: OOP Shortcut Properties & Template Migration (June 2026)
- [x] **OOP Page shortcuts**: Implemented magic getter/setter/isset methods in `Page.php` forwarding flat property queries (such as `$page->lang` or `$page->title`) to nested composed child objects (`Page\Localization`, `Page\Metadata`, `Page\Content`), avoiding 3-level deep nested accesses in templates.
- [x] **String casting on Content**: Added `__toString()` on `Page\Content` class to allow clean template casting (`<?= $page->content ?>`) without warnings.
- [x] **FileProcessor refactoring**: Updated namespaced `FileProcessor` support list checks to match `Support` object configuration.
- [x] **Templates & Helpers migration**: Rewrote all templates under `_template/` and helper functions under `_engine/functions/` to use OOP arrow syntax.
- [x] **ArrayAccess removal**: Cleanly removed `ArrayAccess` interface and implementations from `Page.php`.
- [x] **IDE Static analysis cleanup**: Prepended PHPDoc variable annotations to templates, resolving all "Undefined variable" IDE warnings.

### 🏗️ Phase 3: Directory Structure Refactoring (June 2026)
- [x] **PSR-4 Autoloading**: Configured Composer to autoload classes under the `Indieinabox\` namespace directly from the `app/` folder.
- [x] **Unified Bootstrap**: Created `bootstrap/app.php` to initialize autoloader and procedural helpers/data files, replacing custom loaders.
- [x] **Standardized Paths**: Realigned target workspaces to modern conventions (`public/`, `content/`, `data/`, `resources/views/`, `resources/static/`).
- [x] **Root Build Runner**: Migrated the main site compilation script to a root-level `build.php` executing the generation pipeline.
- [x] **Documentation Refactoring**: Cleaned the main `README.md` and updated all documentation under `docs/` to reflect the new structure.
- [x] **Alternative Content Directory**: Implemented support for using an alternative content directory (separating server code from content to allow different repositories).

### ⚙️ Phase 4: Full Procedural Helpers Migration (June 2026)
- [x] **Namespaced Helpers**: Migrated procedural functions inside `app/functions/` to namespaced classes like `Helper` and static helper methods.
- [x] **Unified Global Wrappers**: Replaced scattered procedural files in `app/functions/` with a single `helpers.php` wrapper file for backward-compatibility with template variables.
- [x] **Structured SiteBuilder**: Migrated the build pipeline execution and static copying logic from procedural functions inside `build.php` to the new `SiteBuilder` class.

### 🔍 Phase 5: Parser Transition (June 2026)
- [x] **MarkdownParser Integration**: Swapped out the legacy procedural `parse()` bridge function for the direct object-oriented usage of `MarkdownParser` in the `SiteBuilder` scanning pipeline and functional tests.
- [x] **Modular Processors**: Cleanly enabled the pipeline to instantiate and call modular namespaced processor classes (`FileProcessor`, `ContentProcessor`, `LanguageProcessor`).

### 🌐 Phase 6: Web / CLI Single-File Entry & Webmentions (June 2026)
- [x] **Web SAPI Routing**: Implemented conditional execution inside `build.php` to handle CLI static page compilation and Web request routing separately based on `php_sapi_name()`.
- [x] **WebRouter Dev Server**: Created `WebRouter` to route requests, serving static files directly from the output directory `public/` and handling webmention endpoints.
- [x] **Webmention Verification Endpoint**: Implemented `WebmentionHandler` to validate and process incoming webmentions via beauty URLs (e.g. `/webmention`) and query parameters.
- [x] **Source Linking Validation**: Enabled automatic fetching and parsing of external source pages to verify presence of absolute or relative back-links to target pages.
- [x] **Aggregated Webmention Storage**: Configured webmentions to be saved under `data/webmentions/<md5_slug>.json` while filtering out duplicate sources.
- [x] **Interactions UI**: Verified that index shows only counters, post page shows likes counter and open comments, clicking likes counter lists likes, and clicking a comment opens the comment page (supporting nested replies).
- [x] **Premium Presentation Layer**: Created an aesthetically rich, responsive HTML/CSS Webmention helper form using CSS backdrop filters, glassmorphism, and HSL tailored dark-mode colors.

### 🔑 Phase 7: Simple IndieAuth Endpoint (June 2026)
- [x] **Hidden Configuration Priority**: Updated configuration loader in `build.php` to prioritize loading settings from `.config.yml` if it exists, securing secrets like passwords in production.
- [x] **Metadata Endpoint Discovery**: Implemented compliant OAuth 2.0 authorization server metadata discovery served dynamically from the site FQDN.
- [x] **Authorization Code & PKCE Validation**: Developed a stateless authorization flow supporting PKCE `S256` and `plain` code challenges and verification.
- [x] **Token Issue & Bearer Verification**: Developed token exchange capabilities and bearer token validation via HTTP `Authorization` headers.
- [x] **Premium Presentation Layer**: Created an aesthetically rich, responsive login layout utilizing Google Fonts, backdrop blur filters, and smooth CSS animations.

### 🛠️ Phase 8: Custom AST-based Markdown Parser (June 2026)
- [x] **Recreation of Parser**: Replaced the legacy Parsedown library with a lightweight, clean-room custom Markdown parser design (`ASTParser`).
- [x] **Abstract Syntax Tree (AST)**: Implemented a two-pass parser architecture (block parsing and inline parsing) that constructs a structured Abstract Syntax Tree (AST).
- [x] **Type Safety**: Created concrete namespaced OOP node classes to ensure type safety.
- [x] **Visitor-pattern HTML Renderer**: Created `HtmlRenderer` to walk the AST nodes and output clean semantic HTML markup.

### 📤 Phase 9: AST-driven Multi-Format Output Engine (June 2026)
- [x] **Flexible Rendering Engine**: Developed a modular renderer that consumes the custom Markdown AST to compile the site's content into multiple protocols/formats simultaneously (HTML, Gemini, Gopher).
- [x] **Static Exporter**: Integrated the output engine into `SiteBuilder` to compile the site for all three formats during the build pipeline.
- [x] **Mosaic Browser Compatibility**: Verified functionality and rendering in the legacy Mosaic browser.
- [x] **Image Dithering & Linking**: Verified that image dithering works correctly and that a visible link to the original high-resolution image is provided (not wrapping the dithered image).

### 📡 Phase 10: Twtxt Publishing & Consuming (June 2026)
- [x] **Twtxt Feed Generation**: Created a builder/renderer that automatically formats site posts/updates into a standard flat-text `twtxt.txt` feed.
- [x] **Feed Aggregator & Consumer**: Built a local twtxt parser that fetches and reads remote twtxt feeds.
- [x] **Hub Integration**: Integrated with federated twtxt hubs to search, query, and retrieve mentions, replies, and updates beyond the local subscription list.
- [x] **Deepened Testing**: Verified twtxt generation and interactions with thorough testing.

### 🗄️ Phase 10.5: SQLite Database Migration (June 2026)
- [x] **Centralized Configuration:** Extinguished the legacy `data/` folder flat-files migrating all application configurations, translations, and globalization mappings to a centralized `indieinabox.sqlite` database.
- [x] **Installation Interface:** Created a dynamic installer that generates the database schema automatically.
- [x] **Single-File Payload:** Refactored `compile.php` to embed the database SQL and installer logic directly into the generated `indieinabox.php` package.
- [x] **PDO Driver Upgrade:** Migrated from native `SQLite3` class to the universally available `PDO_SQLite` driver.

### ✍️ Phase 11: Micropub API & IndieWeb Core Support
- [x] **W3C Micropub Endpoint**: Created the endpoint (`/micropub`) supporting `q=config`, `q=syndicate-to`, `POST` creation requests with frontmatter/YAML, standard properties (`h-entry`, `content`, `category`, etc.), and custom `mp-language`.
- [x] **Microformats Integration**: Implemented proper Microformats across the site (e.g., `h-entry`, `h-card`).
- [x] **IndieWeb Post Types**: Implemented all IndieWeb types such as reply, rsvp, bookmark, etc.
- [x] **Shortlinks Generation**: Generated shortlinks using internal logic or external services like Nullpointer/Picoshare.
- [x] **Media Endpoint**: Created `/micropub/media` with collision detection.
- [x] **Endpoint Discovery**: Added `<link rel="micropub">` and Webmention/Microsub links pointing to local/hosted services.
- [x] **Local Web Client**: Built a local web-based client at `/micropub/client` to allow native dashboard posting via Bearer access tokens.
- [x] **Advanced Client UI**: Implemented dynamic toggles for Post Types and gallery reordering.
- [x] **Dev Server Media Fallback**: Adjusted static routing to serve pre-build media uploads directly from `content/media`.

### 🎨 Phase 12: Whostyles Integration
- [x] **Decentralized Styling**: Integrated the newly updated Whostyles V2 specification.
- [x] **Hash-Based Rendering**: Safely decode Whostyles V2 hashes to extract configuration and color maps.
- [x] **Inline & Content Discovery**: Implemented discovery by parsing `<meta name="whostyle">` tags and extracting bitpacking hashes from content body.

### 📬 Phase 13: Microsub Endpoint & Reader (with Twtxt)
- [x] **Microsub Server**: Implemented a W3C Microsub endpoint to manage feeds, channels, and read states.
- [x] **Twtxt Feeds Bridging**: Enabled subscription to standard `twtxt.txt` feeds alongside RSS, Atom, and Microformats.
- [x] **Token Verification**: Verified bearer tokens using the local IndieAuth endpoint.

### 🌍 Phase 15: Localization & Translation
- [x] **Translation Completeness**: Translated intro and all pending posts from PT to EN/multilingual, ensuring parity.
- [x] **Variable Translation**: Ensured all variables are fully translatable, removing any hardcoded text strings from the layouts.
- [ ] **Translation Mechanism**: Replace `pseudoTranslate()` in `SiteBuilder.php` with a proper translation mechanism (e.g. LLM integration or gettext/machine translation API). Currently it just prefixes the title/content with `[LANG] `.

### 🔎 Phase 16: UI/UX & SEO Architecture
- [x] **Semantic Web & Accessibility**: Verified semantic HTML markup and accessibility compliance.
- [x] **OpenGraph & JSON-LD**: Implemented OpenGraph, Twitter Cards, and JSON-LD structured data following strict dimension guidelines for preview cards (e.g., 1200x630 for OG, 16:9/4:3/1:1 for JSON-LD).
- [x] **Homepage Layout**: Removed the explicit 'Home' title from the homepage, rendering the intro text directly.

### 📝 Phase 17: Content, Tags & Documentation
- [x] **Demo Content Expansion**: Increased the amount of demo posts in `/content`, ensuring at least 2 of each IndieWeb kind/type.
- [x] **Dummy Interactions**: Added dummy interactions (likes, replies, reposts) to at least one article and one note to test the interface.
- [x] **Differentiated Content**: Replaced similar demo articles (e.g., PHP and CSS) with distinct topics.
- [x] **Intro File Configuration**: Configured `intro.md` to be included only in the homepage and not appear as an isolated page or in menus.
- [x] **Garden Tags Enforcement**: Enforced mandatory tags (`confidence`, `maturity`, `importance`, `flowerbed`) for 'garden' type articles with specific defaults.
- [x] **Automatic PHP Documentation**: Generated automatic PHP documentation for all classes, variables, and methods.
- [x] **Method & Class Documentation**: Filled out documentation details for all methods, classes, variables, and functions.

### 🛡️ Phase 18: Admin Panel & Moderation
- [x] **Unified Admin Panel**: Created the Admin Panel, integrating Config, Micropub, and Microsub views into a single unified environment.
- [x] **Comment Moderation UI**: Included comment moderation capabilities within the Admin Panel.
- [x] **Automated Moderation**: Implemented an automated comment moderation system (e.g., Akismet integration).

---

## Future Roadmap

The following next-generation features are scheduled for development:

### 🌐 Phase 14: ActivityPub Federated Protocol (Publishing & Reading)
- [ ] **Actor Profiles & WebFinger**: Implement WebFinger query routing (`/.well-known/webfinger`) and JSON-LD ActivityPub Actor profiles so the site can be searched and followed on the Fediverse (e.g., Mastodon).
- [ ] **Inbox & Outbox Handling**: Create an ActivityPub Inbox/Outbox system supporting HTTP Signatures verification.
- [ ] **Publishing & Reading**: Publish new site posts automatically to followers' inboxes, and utilize the local Microsub endpoint as a centralized hub to fetch, store, and display incoming feed items from the Fediverse.
- [ ] **Extended Protocols**: Investigate possibilities of supporting forum protocols and BookWyrm alongside ActivityPub.

### 🧩 Phase 19: Theme Data Abstraction Layer
- [x] **Autonomous Data Methods**: Refactor the generation of template data (e.g., footer links, page titles, OpenGraph/meta tags) into autonomous helper methods or a dedicated abstraction layer. This will decouple low-level data logic from the presentation layer, allowing future theme designers to build themes easily without writing complex PHP logic.

### 🚀 Phase 20: Incremental Build & Garbage Collection
- [x] **Incremental Generation**: The site builder now caches image processing across builds because `public` directories are no longer wiped entirely before a build.
- [x] **Garbage Collector**: The site builder tracks every generated file (HTML, JSON, feeds, images) in a manifest array. Post-build, it automatically deletes orphaned files and directories that are no longer part of the current site state.

### 📦 Phase 21: Official Distribution & Setup
- [x] **Clean Build Process**: Modify the release build script to generate `index.php` as the final artifact, rather than `indieinabox.php`.
- [x] **Default Production Content**: Define a minimal, out-of-the-box content set for the official distribution (English only, default theme). This should include a "Welcome to indieinabox" article (explaining the Indieweb and app with a link to docs) and one note (highlighting data ownership).
- [x] **Remove Dummy Content**: Ensure the robust dummy content (currently in the `content` folder) is excluded from the final release build, leaving only the default production content.
- [ ] **i18n Out-of-the-box (Backlog)**: Future implementation of translations ready for the end-user.

### 🧪 Phase 22: Dev/Demo Environment
- [ ] **Maintain Dummy Structure**: Keep the current comprehensive `content` directory for development purposes, theme testing, and ensuring all content types and interactions work flawlessly.
- [ ] **Automated Demo Instance**: (Optional) Set up an automated pipeline to deploy the `main` branch with all dummy content to a public demo instance.

### 🔄 Phase 23: Simplified Self-hosting Updates (Dogfooding)
- [ ] **CLI Update Command**: Create a simple CLI mechanism (e.g., a curl script or internal PHP command like `php indieinabox.php update`) that fetches the latest compiled version from the `main` branch and safely overwrites the current instance (preferably with a `.bak` backup).
- [ ] **UI Update Button**: Add an update trigger in the Settings/Config UI that initiates the same update process, allowing for fast, friction-less dogfooding.

### 🌐 Phase 24: Full Integration Testing Ecosystem (Docker)
- [ ] **Isolated E2E Project**: Create a separate repository or dedicated directory outside the main tree for end-to-end integration testing, preventing clutter in the main codebase.
- [ ] **Docker Compose Network**: Configure a comprehensive `docker-compose.yml` to spin up essential fediverse/indieweb platforms for real-world integration testing:
    - [ ] Mastodon
    - [ ] Misskey
    - [ ] Pleroma
    - [ ] Pixelfed
    - [ ] WordPress (with Indieweb plugins)
    - [ ] Lemmy
    - [ ] Bookwyrm
    - [ ] Yarns (Microsub server)
    - [ ] Bridgy (for testing bridging capabilities)
    - [ ] Aperture (another Microsub client)
    - [ ] Indigenous (or similar generic Micropub clients)
- [ ] **Networking & FQDNs**: Set up a reverse proxy (e.g., Traefik/Nginx) or Cloudflare Tunnels to provide valid FQDNs for each local container.
- [ ] **Automated Setup Scripts**: Create scripts to automatically provision test users, generate necessary tokens, and configure instances upon container startup.
