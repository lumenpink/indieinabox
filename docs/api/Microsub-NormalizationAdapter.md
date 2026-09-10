# Microsub\NormalizationAdapter

**File:** `app/Microsub/NormalizationAdapter.php`  
**Namespace:** `Indieinabox\Microsub`

## Overview

`NormalizationAdapter` is a static factory class that transforms raw parsed data from any supported origin protocol into a standardized [`ExtendedEntry`](./Microsub-ExtendedEntry.md) object.

It is the single entry point for converting external post data before it is saved to the local Microsub inbox. This ensures no protocol-specific logic leaks into the feed storage or timeline serving layers.

## Static Methods

### `fromActivityPub(array $json, string $htmlContent): ExtendedEntry`

Converts a raw ActivityPub JSON-LD object (typically a `Note` or `Question` type) into an `ExtendedEntry`.

**Responsibilities:**
- Extracts `uid`, `url`, `published` from standard AP fields.
- Sets `network = 'activitypub'` and extracts `originServer` from the post URL.
- Assigns base capabilities `['reply', 'like', 'repost']`.
- Reads `summary` as `contentWarning` if present.
- Detects `oneOf` / `anyOf` arrays and populates `$poll`, adding `poll_vote` to capabilities.

---

### `fromTwtxt(string $uid, string $url, string $text, int $timestamp, string $authorName): ExtendedEntry`

Converts a raw Twtxt entry into an `ExtendedEntry`.

**Responsibilities:**
- Sets `network = 'twtxt'`.
- Converts plain text to HTML by auto-linking URLs.
- Extracts `#hashtags` into the `category` array via `Helper::extractHashtags()`.
- Capabilities are restricted to `['reply']` only (Twtxt has no native like/repost).

---

### `fromFeed(string $uid, string $url, string $htmlContent, int $timestamp, string $authorName, string $feedUrl): ExtendedEntry`

Converts a raw RSS/Atom/JSON Feed item into an `ExtendedEntry`.

**Responsibilities:**
- Sets `network = 'rss'`.
- Extracts `#hashtags` from stripped HTML text into `category`.
- Performs a **synchronous cache lookup** against `webmention_discovery_cache` to determine whether the feed's domain supports Webmentions.
  - **Supported:** capabilities = `['reply', 'like', 'repost']`
  - **Not supported / unknown:** capabilities = `['local_reply', 'local_like', 'repost']`
  - **Unknown domains** are inserted into the cache with `last_checked = 0` to queue them for async discovery by `BackgroundWorker::processWebmentionDiscovery()`.

## Data Flow

```
Raw AP JSON   ─── fromActivityPub() ─┐
Raw Twtxt     ─── fromTwtxt()       ─┤──▶  ExtendedEntry ──▶  Saved to .md frontmatter
Raw RSS/Atom  ─── fromFeed()        ─┘                         Served by MicrosubHandler
```

## Related

- [`ExtendedEntry`](./Microsub-ExtendedEntry.md) — The output object.
- [`extended_entry_schema.json`](./extended_entry_schema.json) — Formal JSON Schema.
- [`BackgroundWorker`](./BackgroundWorker.md) — Runs `processWebmentionDiscovery()` to update the cache.
- [Examples](./examples/) — Canonical JSON examples for each origin type.
