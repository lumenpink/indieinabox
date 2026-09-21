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
| `cron_token` | `string` | Secret token required to trigger `GET /cron?token=...` externally (can also be set via `CRON_TOKEN` env). |
| `build_token` | `string` | Secret token required to trigger `POST /build?token=...` webhook (can also be set via `BUILD_TOKEN` env). |

### Language & Translation Routing

* The first language in the `lang` array is always the **Main (Default)** translation route (`defaultlang`), rendered at the site root (`/`).
* Secondary languages are rendered under localized prefix directories (e.g. `/es/`, `/pt/`).
* In the Web Admin panel (`/admin/config`), languages can be reordered at any time using **Move Up (▲)** and **Move Down (▼)**, or promoted to primary directly using the **Make Main** button.
* **Locale Dictionaries & Auto-Fill:**
  * Bundled locale definitions are provided for `pt` (Português), `es` (Español), and `en` (English) under `resources/locales/`.
  * When a new language is added to the configuration, missing translations and kind titles (e.g. `Artigos`, `Notas`) are automatically populated from the matching locale dictionary.
  * If a dictionary is not yet available locally, Indieinabox attempts to download it from the official Codeberg repository and caches it in `data/locales/`.
  * Regional language codes (such as `pt-BR` or `es-ES`) automatically resolve to their primary language dictionary (`pt` or `es`).
  * An **Auto-fill from Locales** button in `/admin/config` allows refreshing/filling all empty translations at any time.

---

## 🚀 CLI Actions & Switches

When running the pipeline via terminal, you can pass a primary action command and optional modifier flags.

### Available Actions

```bash
php indieinabox.php [action]
```

*   **`build`** (default): Generates the static site.
*   **`setup`**: Configures the instance atomically in a single command using parameters or defaults (shared with the web installer via `InstallService`):
    *   `--name <sitename>`: Set website / blog name (default: `Indie In A Box`).
    *   `--db <path>`: SQLite database file path (default: `data/indieinabox.sqlite`).
    *   `--data-dir <dir>`: Directory path for database and inbox feeds (default: parent directory of db).
    *   `--lang <code[,code]>`: Primary / default language or comma-separated languages (default: `en`).
    *   `--content <dir>`: Source content directory path (default: `content`). Supports relative directory names or absolute filesystem paths.
    *   `--password <pass>`: Web UI / IndieAuth admin password. If omitted, a secure 16-character password is automatically generated and displayed in the terminal.
    *   `--fqdn <url>`: Fully Qualified Domain Name / base site URL (default: `http://localhost:8080`).
    *   `--author <name>`: Author / owner display name (default: same as site name).
    *   `--no-build`: Skip the automatic static site build at the end of the installation sequence.
    *   `--non-interactive` / `-y`: Explicit headless flag.
*   **`config set/get`**: Sets or retrieves a configuration variable directly to/from the database (e.g. `config set --key <k> --value <v>`).
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
