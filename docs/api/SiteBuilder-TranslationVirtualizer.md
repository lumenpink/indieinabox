# TranslationVirtualizer
**Namespace:** `Indieinabox\SiteBuilder`

Class TranslationVirtualizer

Handles multilingual parity checks and virtualizes missing page translations
by generating pseudo-translated pages to maintain consistent site navigation across languages.

## Methods

### __construct()
```php
public function __construct(Site $site)
```
Initializes the virtualizer with site configuration.

### virtualize()
```php
public function virtualize(Pages $pages): void
```
Evaluates all pages across all configured active languages.
- If `translation_auto` is disabled and parity is required, throws `RuntimeException`.
- If `translation_auto` is `pseudo`, creates virtual clones with `[LANG]` prefixes for missing translations.
- Honors `translation_parity_rule` (`from-main-only` vs bidirectional).

### pseudoTranslate()
```php
public function pseudoTranslate(Page $page, string $targetLang): void
```
Applies pseudo-translation formatting to a page:
- Prefixes title with `[LANG]` (or body if kind has no title, such as notes).
- Updates language codes and URL slugs.
- Marks page with `translated_by_ia = true`.
