# Core Namespaced Classes

This document describes the primary object-oriented classes under the `Indieinabox` namespace, their roles, and how they compose the generator architecture.

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

### OOP Shortcut Properties:
To keep templates clean, the `Page` class implements magic shortcuts for properties. Instead of writing `$page->localization->lang`, templates can write `$page->lang`.
* **Magic Getter/Setter (`__get`, `__set`)**: Forwards flat property requests directly to the composed `localization`, `metadata`, or `content` child objects.
* **Dynamic `isodate`**: Returns the formatted ISO-8601 representation of the page's `$date` property on the fly.
* **`Content::__toString()`**: The `Page\Content` object implements `__toString()` returning its `$content` body. This makes rendering simple in templates (e.g., `<?= $page->content ?>`) while keeping the object references clean elsewhere.

---

## 🌐 Site (`Indieinabox\Site`)

The `Site` class serves as the root configuration settings block loaded from `config.yml`. It aggregates config namespaces:

* **`Site\Metadata`**: High-level details (`$title`, `$sitename`, `$author`, `$defaultTitle`, `$fqdn`).
* **`Site\Paths`**: Workspace directories (`$baseDir`, `$outputDir`, `$contentDir`).
* **`Site\Options`**: Generation options (`$buildAll`, `$dev`, `$skipStatic`, `$forceStaticOverride`, `$htmlpostprocessing`).
* **`Site\Localization`**: Locales settings (`$lang` array, `$defaultLang`).
* **`Site\Support`**: Valid extensions list (`$support` array, `$defaultCategory`).

### Magic Getter (`__get`):
Forwards requests for common configurations (e.g. `$site->dev`, `$site->defaultlang`, `$site->outputdir`) directly to the respective child option, paths, or localization objects.

---

## 📚 Pages Collection (`Indieinabox\Pages`)

Extends `ArrayObject` to hold lists of `Page` objects.
* **`add(Page|array $page, ?string $id)`**: Appends a page to the collection using its slug as the key. Supports both raw arrays and Page objects for legacy compatibility.
* **`all()`**: Returns the raw array map of slug -> Page objects (used during lists filtering or custom sorting).
* **`get(string $id)`**: Retrieves a Page object by its slug key.

---

## 🔍 Parser & Processors

### `Indieinabox\MarkdownParser`
The main parser class that orchestrates scanning and splitting source files. It delegates parsing steps to:
* **`Markdown\FileProcessor`**: Validates extensions using `$site->support->support` and resolves template layout files.
* **`Markdown\ContentProcessor`**: Extracts frontmatter using `Yaml`, sanitizes inline tags (`#tag`), and converts markdown using `Parsedown`.
* **`Markdown\LanguageProcessor`**: Determines the active page language and translated paths.

### `Indieinabox\Parsedown`
Extends and modularizes markdown parsing into specific classes:
* **`Parsedown\BlocksParser`**: Handles block structures (tables, lists, headers, code blocks).
* **`Parsedown\InlinesParser`**: Handles inline styles (bold, links, images).
* **`Parsedown\ElementsHandler`**: Handles markup escaping.

### Utility Classes:
* **`Indieinabox\Yaml`**: Reads/writes configuration and frontmatter YAML files.
* **`Indieinabox\Helper`**: General utilities (slug conversion, localized date maps).
* **`Indieinabox\Translations\UrlTranslations`**: Resolves translated paths and nicks for multilang pages.

---

## 🔀 Web Router (`Indieinabox\WebRouter`)

The `WebRouter` orchestrates requests when the application runs under a Web SAPI (e.g. `cli-server`, `fpm-fcgi`).
*   **Routing**: Detects requests to beauty URLs (like `/webmention` or `/webmentions`) and parameter requests (like `?webmention`). These are passed to `WebmentionHandler`.
*   **Static Asset Server**: Serves static pages and assets (HTML, CSS, JS, images, SVG, XML, JSON) directly from the output directory (`public/`) to function as a live dev/production server.

---

## 📩 Webmention Handler (`Indieinabox\WebmentionHandler`)

The `WebmentionHandler` manages the reception, validation, and storage of incoming webmentions.
*   **GET Requests**: Serves an aesthetically premium instruction and form page allowing users to manually submit webmentions.
*   **POST Requests**:
    1.  Validates formats of `source` and `target` URLs.
    2.  Verifies the `target` URL points to the configured FQDN domain of this site.
    3.  Confirms the `target` page exists as a generated file in the output directory.
    4.  Fetches the `source` page and parses its HTML to verify it contains a valid absolute or relative link to the `target`.
    5.  Stores successfully verified webmention data in JSON format under `data/webmentions/<md5_slug>.json`, aggregating mentions without duplicating sources.
