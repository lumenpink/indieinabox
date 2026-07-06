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
| `outputdir` | `string` | Output folder where generation is written (default: `public`). |
| `buildall` | `bool` | If true, compiles all documents. |
| `htmlpostprocessing` | `string` | Minification / Beautification mode (`"minify"` or `"beautify"`). |
| `lang` | `array` | Supported language locales list (e.g. `[pt-br, en, es]`). |
| `defaultlang` | `string` | The default language translation route. |
| `support` | `array` | Extensions list processed by generator (e.g. `[md, txt, html]`). |
| `defaultcategory` | `string` | Category fallback value for pages. |

---

## 🚀 CLI switches (`build.php`)

When running the pipeline via terminal, flags modify compilation options:

```bash
php build.php [-d] [-s] [-f]
```

*   **`-d` (Development Mode)**:
    - Enables dev flags in templates.
    - Automatically injects the `live.js` live-reload script in headers.
    - Forces HTML post-processing to `"beautify"` format.
*   **`-s` (Skip Static Files)**:
    - Skips copying assets from `resources/static/` directory to save build time.
*   **`-f` (Force Overwrites)**:
    - Overwrites generated layout templates and outputs forcefully.

---

## 📦 Global Runtime Variables

The following variables are loaded into the global scope and available inside all layout templates (`resources/views/*.php`):

*   **`$site`** (`\Indieinabox\Site`): Central configuration settings object.
*   **`$page` / `$p`** (`\Indieinabox\Page`): Current parsed page class representation being processed in the loop.
*   **`$pages`** (`\Indieinabox\Pages`): The collection containing all parsed pages in the build pipeline.
*   **`$kinds`** (`array`): Associative map of classification kinds and their vector/image icons.
*   **`$base`** (`string`): Absolute path to the workspace root directory.
