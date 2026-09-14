# Indieinabox - Social Personal Website Swiss Knife

Indieinabox is a lightweight, modular static site generator (SSG) built in PHP,
tailored for personal and social websites with native support for multi-language
 content, localized date formatting, HTML minification/beautification, and full
support for IndieWeb principles (Micropub API, IndieAuth, Webmentions, and Whostyle JSON).

---

## 📖 Technical Documentation

All detailed technical documentation has been separated into dedicated markdown files under the `docs/` folder:

* **[Project Architecture](file:///home/lumen/indieinabox/docs/architecture.md)**: Details the compilation
    pipeline flow and workspace directory structures.
* **[Core Classes](file:///home/lumen/indieinabox/docs/classes.md)**: Explains namespaced PHP objects
    (Site, Page, Pages, Parsedown) and the magic property shortcut layer.
* **[Procedural Functions](file:///home/lumen/indieinabox/docs/functions.md)**: Documents legacy helper routines
    and date/translation mechanisms.
* **[Configuration & CLI Options](file:///home/lumen/indieinabox/docs/configuration.md)**: Details `config.yml`
    keys, command-line arguments, and global variables.
* **[Roadmap & Refactoring History](file:///home/lumen/indieinabox/docs/roadmap.md)**: Tracks completed and
    upcoming refactoring steps.
* **[Custom Types & Languages](file:///home/lumen/indieinabox/docs/custom_types.md)**: Instructions on how to
    add new languages and post kinds (`notes`, `photos`, `garden`) to the blog.

---

## 🚀 Running the Project

### Installation

Make sure you have PHP (7.4 to 8.4+) and Composer installed:

```bash
composer install
```

### Running Commands

To compile the static site or run background tasks:

```bash
# Execute standard build
php indieinabox.php build

# Execute development build (with live-reload script injections)
php indieinabox.php build -d

# Skip copying static assets
php indieinabox.php build -s

# Force overwrite of static files
php indieinabox.php build -f

# Force rebuild from zero (ignore partials)
php indieinabox.php build -a

# Rebuild from zero (minus media)
php indieinabox.php build -a -M

# Only rebuild media (minus pages)
php indieinabox.php build -m

# Fetch RSS/Twtxt feeds manually
php indieinabox.php fetch

# Run pending background tasks (webmentions, retries)
php indieinabox.php cron
```

The output static files will be compiled and written to the `public/` directory (when using the build command).

### Single-File Application

Indieinabox can be compiled into a single drop-in PHP file for easy deployment:

```bash
# Compile to a single file
php compile.php
```

This will create `indieinabox.php` which embeds all logic, the SQLite database configuration, and an installer.

### Testing and Linting

The repository comes with development QA tools:

```bash
# Run unit tests (Pest PHP)
composer test

# Run code linter and compatibility checks
composer sniffer

# Run native local link checker against the compiled site
php indieinabox.php test-links
```

---

## 🌐 IndieWeb Standards & Compliance

Indieinabox follows W3C and IndieWeb recommendations with verified interoperability against official test vectors:
- **Webmention ([W3C Recommendation](https://www.w3.org/TR/webmention/))**: Strict discovery precedence (HTTP `Link` > HTML `<link>` > `<a>`), receiver verification, relative link resolution, and sender delivery tested against [webmention.rocks](https://webmention.rocks/) and [IndieWebify.me](https://indiewebify.me/).
- **Microformats 2 ([microformats.org](https://microformats.org/wiki/microformats2))**: Rich parsing of `h-entry`, `h-card` (author photo, name, profile URL), `u-like-of`, `u-repost-of`, `u-in-reply-to`, and `p-rsvp`.
- **IndieAuth & Micropub**: Interoperable with IndieWeb clients (Indigenous, Quill, Micro.blog).
- **Testing & Verification Guide**: See [`docs/webmention_testing.md`](docs/webmention_testing.md) for step-by-step instructions on running manual and automated tests with `webmention.rocks`, `IndieWebify.me`, and the built-in `test-webmention` CLI command.

---

## 💖 Acknowledgments & Credits

Indieinabox stands on the shoulders of giants in the open web and IndieWeb communities:
- **Microformats 2 Parser (`mf2/mf2`)**: Immense thanks to the maintainer trio **Barnaby Walters** ([@waterpigs](https://github.com/barnabywalters)), **Tantek Çelik** ([@tantek](https://github.com/tantek)), and **Aaron Parecki** ([@aaronpk](https://github.com/aaronpk)) for creating and stewarding `php-mf2`, enabling lightweight, standard-compliant structured data parsing across the PHP IndieWeb.
- **IndieWeb Community**: For continuous inspiration, standards design, and testbeds like [webmention.rocks](https://webmention.rocks/).

