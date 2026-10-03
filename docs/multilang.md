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

## AI Virtualization & Two-Pass Auto-Translation Architecture

When a post exists in one language but lacks a translation in another, IndieInABox can automatically "virtualize" and translate the missing page using either a lightweight mock (`pseudo`) or intelligent LLMs (Google Gemini, OpenAI GPT, Anthropic Claude, xAI Grok, Ollama).

The AI auto-translation pipeline follows an enterprise-grade **Two-Pass Architecture**:

### Pass 1: Structural Metadata & Taxonomy Pre-Translation (SQLite Persistence)
Before sending article bodies to the AI:
1. **Titles & Slugs**: Translates post titles and derives localized slugs, storing them in SQLite (`ai_taxonomy_translations` and `url_translations`). This ensures 100% deterministic permalinks that never fluctuate across builds or model temperature variations.
2. **Taxonomies**: Pre-translates digital garden flowerbeds (`flowerbed` / canteiros), `tags`, and `categories`, persisting the term dictionary in SQLite.

### Pass 2: Pure Body Translation with Positional Masking & Sanitization
1. **Zero-Frontmatter Token Optimization**: Only the pure Markdown body is sent to the AI engine (never YAML frontmatter). This cuts prompt token overhead by up to 40% and completely avoids YAML parsing errors.
2. **Positional Link & Code Masker**: Before transmission, Markdown links (`[text](url)`), images (`![alt](url)`), inline code (`` `code` ``), and code blocks (```` ```code``` ````) are masked into positional tokens (`__LNK_TARGET_0__`, `__IMG_TARGET_0__`, `__CODE_BLOCK_0__`).
3. **Internal Cross-Link Rewriting**: Internal links are rewritten by position to point to the target language and the Pass 1 translated slug before restoration.
4. **AI Response Sanitizer**: Strips LLM conversational preambles (e.g., *"Here is the translated article:"*, *"Tradução para português:"*), conversational sign-offs (e.g., *"Hope this helps! Let me know what you think"*, *"Qualquer dúvida estou à disposição"*), and accidental outer ` ```markdown ` code fences.
5. **Positional Token Restoration**: Masked tokens are restored strictly by position via `strtr`, guaranteeing that link targets and code blocks are never altered, dropped, or corrupted by the model.

### Frontmatter Metadata & AI Disclaimer
Virtualized pages automatically receive standardized frontmatter metadata:
- `translated_by: ai`
- `translated_by_ia: true`
- `translation_ai_provider`: Provider name (e.g. `Google Gemini`, `Anthropic Claude`, `OpenAI GPT`)
- `translation_ai_model`: Model identifier (e.g. `gemini-2.5-flash`, `claude-3-5-haiku-20241022`)
- `translation_source_lang`: Source language code (e.g. `en`)
- `translation_date`: ISO date of translation (`YYYY-MM-DD`)

Themes display an elegant, localized disclaimer using `ThemeHelper::getAITranslationNotice()`, identifying the AI provider and model used while preserving transparency for readers.

By explicitly providing translated Markdown files and correlating them via `url_translations`, you bypass virtualization entirely, resulting in 100% manual native localization.

## Bundled Locales & Auto-fill

IndieInABox bundles locale packages (`resources/locales/*.json`) for official languages (`pt`, `es`, `en`, `fr`, `de`, etc.), which are compiled directly into the binary (`EmbeddedLocales`).

Each locale package contains:
- **`translations`**: Global UI strings (e.g. `Recent posts`, `Flowerbed`, `Maturity`, `Confidence`).
- **`kinds`**: Localized titles and content directory folder slugs for all supported content kinds (e.g. `Articles`/`artigos`, `Notes`/`notas`, `Digital Garden`/`jardins`, `Replies`/`respostas`, `Photos`/`fotos`, `Videos`/`videos`, etc.).

In the Admin Panel (**Settings → Localization**), clicking **"Auto-fill from Locales"** automatically populates missing translations as well as default/fallback English kind labels and folder paths into the target languages without overwriting custom user-defined taxonomies.
