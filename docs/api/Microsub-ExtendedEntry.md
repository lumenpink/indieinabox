# Microsub\ExtendedEntry

**File:** `app/Microsub/ExtendedEntry.php`  
**Namespace:** `Indieinabox\Microsub`

## Overview

`ExtendedEntry` is the **Universal Post Object** of Indieinabox. It represents a single post retrieved from any federated source — ActivityPub, Twtxt, or RSS/Atom — in a normalized, protocol-agnostic structure.

It is designed to be fully compatible with the [JF2 spec](https://www.w3.org/TR/jf2/) used by Microsub, while housing rich protocol-specific metadata under the custom `_indieinabox` namespace key. This allows a generic Microsub client to consume the standard fields without breakage, while a custom client (like the Indieinabox reader) can take advantage of the extended metadata for richer interactions.

## Properties

| Property | Type | Description |
|---|---|---|
| `$type` | `string` | Always `"entry"` (JF2 standard) |
| `$uid` | `string` | Unique identifier for the post (usually the canonical URL) |
| `$url` | `string` | Canonical URL to the original post |
| `$published` | `string` | ISO 8601 datetime string |
| `$author` | `?array` | JF2 Card: `['type' => 'card', 'name' => '...', 'photo' => '...', 'url' => '...']` |
| `$content` | `array` | `['html' => '...', 'text' => '...']` — includes graceful degradation for CW and Polls |
| `$category` | `array` | Hashtags and categories, auto-extracted from content |
| `$network` | `string` | Origin protocol: `"activitypub"`, `"twtxt"`, or `"rss"` |
| `$originServer` | `string` | Domain of origin, e.g. `"mastodon.social"` |
| `$capabilities` | `array` | What interactions are available. See Capability Matrix below. |
| `$contentWarning` | `?string` | Content Warning summary text, or `null` |
| `$poll` | `?array` | Poll structure (options + votes), or `null` |
| `$reels` | `?array` | Short-form video metadata (Pixelfed), or `null` |
| `$isRead` | `bool` | Internal read-state flag |

## Capability Matrix

The `$capabilities` array controls which interaction buttons are available in the UI for a given post. Capabilities are assigned per protocol by `NormalizationAdapter` and may be upgraded asynchronously after Webmention discovery.

| Capability | Description | AP | Twtxt | RSS (w/ Webmention) | RSS (no Webmention) |
|---|---|:---:|:---:|:---:|:---:|
| `reply` | Send a native reply | ✅ | ✅ | ✅ | ❌ |
| `like` | Send a native like/reaction | ✅ | ❌ | ✅ | ❌ |
| `repost` | Boost/announce the post | ✅ | ❌ | ✅ | ✅ |
| `poll_vote` | Vote in a poll | ✅* | ❌ | ❌ | ❌ |
| `local_reply` | Reply saved locally only | ❌ | ❌ | ❌ | ✅ |
| `local_like` | Like saved locally only | ❌ | ❌ | ❌ | ✅ |

> *`poll_vote` is only added for AP posts that contain `oneOf` or `anyOf` arrays in the JSON-LD payload (Question type).

## JF2 Output Format (`toJF2Array()`)

`toJF2Array()` produces the full JF2 payload served by the Microsub `/timeline` endpoint. Standard fields come first, followed by the `_indieinabox` extension key.

### Graceful Degradation

When `_indieinabox` metadata is present, the method also injects semantic HTML fallbacks into `content.html` so generic Microsub clients (without knowledge of the extension) still display content correctly:

- **Content Warning:** Wraps content in `<div class="cw-fallback"><details><summary>CW: ...</summary>...</details></div>`. The custom client hides this with CSS and renders its own styled version.
- **Poll:** Appends a `<div class="poll-fallback"><ul>...</ul></div>` with option names and vote counts. The custom client hides this and renders interactive progress bars instead.
