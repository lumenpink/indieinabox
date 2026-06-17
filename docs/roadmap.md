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

## Future Roadmap

All scheduled phases of the refactoring roadmap, including single-file CLI/Web execution and Webmention endpoints, are now complete.

