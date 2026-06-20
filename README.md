# Indieinabox - Social Personal Website Swiss Knife

Indieinabox is a lightweight, modular static site generator (SSG) built in PHP,
tailored for personal and social websites with native support for multi-language
 content, localized date formatting, HTML minification/beautification, and full
support for IndieWeb principles (Micropub API, IndieAuth, Webmentions, and Whostyle JSON).

---

## 📖 Technical Documentation

All detailed technical documentation has been separated into dedicated markdown files under the `docs/` folder:

*   **[Project Architecture](file:///home/lumen/indieinabox2026/docs/architecture.md)**: Details the compilation pipeline flow and workspace directory structures.
*   **[Core Classes](file:///home/lumen/indieinabox2026/docs/classes.md)**: Explains namespaced PHP objects (Site, Page, Pages, Parsedown) and the magic property shortcut layer.
*   **[Procedural Functions](file:///home/lumen/indieinabox2026/docs/functions.md)**: Documents legacy helper routines and date/translation mechanisms.
*   **[Configuration & CLI Options](file:///home/lumen/indieinabox2026/docs/configuration.md)**: Details `config.yml` keys, command-line arguments, and global variables.
*   **[Roadmap & Refactoring History](file:///home/lumen/indieinabox2026/docs/roadmap.md)**: Tracks completed and upcoming refactoring steps.

---

## 🚀 Running the Project

### Installation

Make sure you have PHP (7.4 to 8.4+) and Composer installed:

```bash
composer install
```

### Static Site Generation

To compile the static site from your content files:

```bash
# Execute standard build
php _engine/build.php

# Execute development build (with live-reload script injections)
php _engine/build.php -d

# Skip copying static assets
php _engine/build.php -s

# Force overwrite of static files
php _engine/build.php -f
```

The output static files will be compiled and written to the `_site/` directory.

### Testing and Linting

The repository comes with development QA tools:

```bash
# Run unit tests (Pest PHP)
composer test

# Run code linter and compatibility checks
composer sniffer
```
