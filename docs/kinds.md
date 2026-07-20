# Post Kinds and Presentation in IndieInABox

IndieInABox is built around IndieWeb principles, treating different types of posts as distinct "Kinds". Each Kind has its own directory and presentation rules.

## Configured Kinds

Kinds are registered in the SQLite database (`kinds` table) with a JSON configuration payload. By default, the demo includes:
- `article`: Long-form posts with titles.
- `note`: Short-form status updates (no title).
- `photo`: Image-centric posts (no title).
- `reply`: Direct interactions replying to external or internal URLs.
- `bookmark`: Links to external resources.
- `jardim` (Garden): Evergreen notes acting as a digital garden. Garden posts (**MUST**) possess 4 specific metadata tags for categorization. If missing, they fallback to default values:
  - `flowerbed`: (Array) Default `['general']`.
  - `confidence`: Default `'possible'`. (Options: `certain`, `likely`, `possible`, `unlikely`, `impossible`).
  - `maturity`: Default `'sprout'`. (Options: `sprout`, `seedling`, `tree`, `wilted`, `stone`).
  - `importance`: Default `'trivial'`. (Options: `trivial`, `minor`, `moderate`, `major`, `critical`).
- `listen`, `read`, `watch`: Media consumption logs.
- `like`, `repost`: Social interactions.

## Kind Configuration Options

The `config_json` payload for a kind controls how it is displayed:

- `content_dir`: The directory name where posts of this kind are stored (e.g., `photos` for `photo`).
- `has_title`: (Boolean) Determines whether this kind inherently possesses a title. Kinds like `note` and `photo` typically have this set to `false`. When `has_title` is `false`, the platform automatically displays a text snippet and a thumbnail (if applicable) instead of the full post content in summary views.
- `show_on_home`: (Boolean) Determines whether posts of this kind appear in the main home stream (homepage).
- `show_in_menu`: (Boolean) Determines whether this kind is listed in the site's footer navigation menu and if its structural indices are generated. If set to `false`, the kind won't have a listing page, and its descriptor (e.g. `[BOOKMARK]`) in individual posts won't be a hyperlink. Note: Kinds that have zero posts are also automatically hidden from the menu and won't generate an index page.
- `display_mode`: Controls how indices and summaries render the kind. E.g., `thumbnail_snippet` renders visual grids for photos.

## Template Integration

When rendering a specific kind, the template engine automatically looks for `resources/views/includes/summary.php` (and optionally custom templates if defined).

- **Snippets & Thumbnails**: For kinds lacking a title (`has_title = false`), `summary.php` extracts the first ~200 characters of plaintext to act as a summary, and detects any dithered image (`_global.gif`) to swap for its thumbnail (`_thumb.gif`).
- **IndieWeb Context**: The `resources/views/page.php` layout automatically injects context metadata (e.g., `in_reply_to`, `bookmark_of`) at the top of the post to provide native semantic HTML (`u-in-reply-to`, etc.) for webmention consumers.
