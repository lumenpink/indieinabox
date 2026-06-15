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

## Future Roadmap

### 📦 Phase 3: Documentation Restructuring (Current Phase)
*   Clean the main `README.md` file from detailed codebase mappings, configurations, and roadmaps.
*   Move technical details into dedicated Markdown files inside `docs/` (`architecture.md`, `classes.md`, `functions.md`, `configuration.md`, `roadmap.md`).

### ⚙️ Phase 4: Full Procedural Helpers Migration
*   Migrate procedural functions inside `_engine/functions/` (such as `kind()`, `translate()`, `localizeddate()`) to static helper methods or namespaced classes (e.g., `Helper`).
*   Transition from procedural require/include loading blocks inside `build.php` to structured service classes.

### 🔍 Phase 5: Parser Transition
*   Replace procedural `MarkdownParser` class inside `_engine/functions/parse.php` with namespaced parser `MarkdownParser` inside `_engine/classes/MarkdownParser.php`.
*   Enable pipeline to directly run modularized namespaced processor classes (`FileProcessor`, `ContentProcessor`, `LanguageProcessor`).
