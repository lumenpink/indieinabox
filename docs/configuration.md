# Configuration & CLI Options

This document outlines options for configuring the site generator, custom CLI flags, and the global template variables.

---

## ⚙️ Site Configuration (`config.yml`)

The primary generator settings are loaded from `config.yml` in the project root:

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `title` | `string` | Default fallback title of pages if not declared in frontmatter. |
| `sitename` | `string` | General website site name. |
| `author` | `string` | Owner/Author name. |
| `fqdn` | `string` | Absolute URL (FQDN) of the deployed site (e.g. `https://lumen.pink`). |
| `contentdir` | `string` | Root folder containing source documents. Can be a relative path (e.g. `content`) or an absolute path on the server. |
| `outputdir` | `string` | Base prefix for output folders where generation is written. E.g., setting it to `public` generates `public_html`, `public_gemini`, `public_gopher`, and `public_media` (default: `public`). |
| `buildall` | `bool` | If true, compiles all documents. |
| `htmlpostprocessing` | `string` | Minification / Beautification mode (`"minify"` or `"beautify"`). |
| `lang` | `array` | Supported language locales list (e.g. `[pt-br, en, es]`). |
| `defaultlang` | `string` | The default language translation route. |
| `support` | `array` | Extensions list processed by generator (e.g. `[md, txt, html]`). |
| `defaultcategory` | `string` | Category fallback value for pages. |

---

## 🚀 CLI Actions & Switches

When running the pipeline via terminal, you can pass a primary action command and optional modifier flags.

### Available Actions

```bash
php indieinabox.php [action]
```

*   **`build`** (default): Generates the static site.
*   **`fetch`**: Forces a manual fetch of all followed RSS/Twtxt feeds.
*   **`cron`**: Runs pending background tasks (such as retrying failed webmentions).

### Compilation Flags

When using the `build` action (or no action), the following flags modify compilation options:

```bash
php indieinabox.php build [-d] [-s] [-f] [-a] [-m] [-M]
```

*   **`-d` (Development Mode)**:
    - Enables dev flags in templates.
    - Disables minification and forces HTML post-processing to `"beautify"` format for readability.
    - Enables incremental partial builds.
    - Injects the `live.js` live-reload script into headers/footers.
    - *Note: To keep the repository minimal and secure, the `live.js` file is not bundled by default. When Dev Mode is enabled via the Web UI, the system automatically downloads it on-demand to the local `data/` directory.*
*   **`-s` (Skip Static Files)**:
    - Skips copying assets from `resources/static/` directory to save build time.
*   **`-f` (Force Overwrites)**:
    - Overwrites generated layout templates and outputs forcefully.
*   **`-a` (Force Full Rebuild)**:
    - Forces a rebuild from scratch, ignoring partial/incremental checks.
*   **`-M` (Skip Media)**:
    - Rebuilds the site but skips media processing.
*   **`-m` (Only Media)**:
    - Skips generation of pages and static files, only processing media.

---

## 📦 Global Runtime Variables

The following variables are loaded into the global scope and available inside all layout templates (`resources/views/*.php`):

*   **`$site`** (`\Indieinabox\Site`): Central configuration settings object.
*   **`$page` / `$p`** (`\Indieinabox\Page`): Current parsed page class representation being processed in the loop.
*   **`$pages`** (`\Indieinabox\Pages`): The collection containing all parsed pages in the build pipeline.
*   **`$kinds`** (`array`): Associative map of classification kinds and their vector/image icons.
*   **`$base`** (`string`): Absolute path to the workspace root directory.
