# Multilanguage Architecture in IndieInABox

IndieInABox is designed from the ground up to support fully isolated, natively correlated multilanguage content.

## Overview

The engine supports multiple languages via the SQLite `settings` table (key `lang`). The first language in the array is considered the "default language".
For example: `["en", "pt"]` makes English the default language, and Portuguese a secondary language.

## Directory Structure

Content in the default language (e.g., `en`) is placed directly inside its respective kind folders (e.g., `content/articles/welcome.md`).
Content for secondary languages (e.g., `pt`) is placed inside a subfolder matching the language code (e.g., `content/pt/articles/bem-vindo.md`).

## URL Correlation (url_translations)

To correlate posts that exist in multiple languages (and specifically, posts that have localized URLs like `about.md` and `sobre.md`), IndieInABox uses the `url_translations` table in the SQLite database.

**Table structure:**
- `lang`: The secondary language code (e.g., `pt`)
- `slug_key`: The base slug of the default language (e.g., `about`)
- `slug_value`: The translated slug for the secondary language (e.g., `sobre`)

When the `SiteBuilder` compiles the site, it queries this table. If it sees `about.md`, it knows its Portuguese counterpart is `pt/sobre.md`. This drives the language switcher in the site header, ensuring it points to the correct localized URL.

## AI Virtualization (pseudoTranslate)

If a post exists in one language but lacks a translation in another, the `SiteBuilder` will automatically "virtualize" a fallback page for the missing language.
It uses `pseudoTranslate` to duplicate the content and prepend a language tag (e.g., `[EN]`) to the title and body, allowing the site to maintain structure while indicating that a manual translation is still required.

By explicitly providing translated Markdown files and correlating them via `url_translations`, you bypass virtualization entirely, resulting in 100% isolated menus and proper native localization.
