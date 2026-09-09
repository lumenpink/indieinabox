# Microsub Extended Post Object Architecture

Indieinabox utilizes the standard W3C Microsub protocol to deliver feeds and posts to clients. Internally, and for advanced bespoke clients, the system implements an extended JF2 (JSON Feed 2.0) object model. 

This document explains how we maintain 100% interoperability with generic standard clients while gracefully enhancing the experience with complex modern federated features (such as Polls, Content Warnings, and Reels from ActivityPub/Twtxt).

## Core Philosophy: Progressive Enhancement

Instead of creating a non-standard protocol, Indieinabox adheres to the "Embrace and Extend" philosophy:
1. **Generic Clients (e.g., Indigenous, Monocle)**: Receive standard, perfectly compliant JF2 payloads. Complex interactive structures are gracefully downgraded to standard HTML elements (e.g., `<details>` for Content Warnings, lists for Polls).
2. **Indieinabox Custom Client**: Parses the custom `_indieinabox` JSON namespace to render native interactive UI components.

## The `_indieinabox` JF2 Extension

The custom namespace `_indieinabox` is attached to every JF2 entry delivered by the local Microsub endpoint.

### Schema Example

```json
{
  "type": "entry",
  "uid": "https://mastodon.social/@user/123",
  "url": "https://mastodon.social/@user/123",
  "published": "2026-09-08T21:00:00Z",
  "author": {
    "type": "card",
    "name": "User Name",
    "url": "https://mastodon.social/@user"
  },
  "content": {
    "html": "<details><summary>CW: Movie Spoilers</summary><p>The ending was crazy!</p></details>",
    "text": "CW: Movie Spoilers. The ending was crazy!"
  },
  "_indieinabox": {
    "network": "activitypub",
    "origin_server": "mastodon.social",
    "capabilities": ["reply", "like", "repost"], 
    "content_warning": "Movie Spoilers",
    "poll": {
      "multiple_choice": false,
      "options": [
        { "title": "Yes", "votes": 10 },
        { "title": "No", "votes": 2 }
      ]
    },
    "reels": null
  }
}
```

### Properties

- **`network`** (`string`): The origin protocol. Expected values: `activitypub`, `twtxt`, `rss`, `micropub_local`.
- **`origin_server`** (`string`): The domain name of the origin instance (e.g., `mastodon.social`).
- **`capabilities`** (`array`): An array of supported interactions for this specific post. Used by the UI to hide/disable buttons (e.g., preventing a user from replying with a poll to a twtxt thread). Available capabilities: `reply`, `like`, `repost`, `poll_vote`.
- **`content_warning`** (`string`|`null`): The subject/summary of the sensitive content. If present, the custom UI should blur the main content.
- **`poll`** (`object`|`null`): Poll definitions and current vote counts.
- **`reels`** (`object`|`null`): Metadata for short-loop videos (common in Pixelfed integrations).

## Fallback Mechanisms

To ensure generic Microsub clients do not break and can still present the content legibly:

1. **Content Warnings**: The backend normalization adapter wraps the actual HTML content inside a `<details><summary>Warning Text</summary>...</details>` block. Standard HTML renderers will make it collapsible natively.
2. **Polls**: The adapter appends the poll options as a plain HTML list `<ul>` at the end of the `content.html`.

This ensures that while standard clients cannot natively "vote", they can still read the context of the poll and the content warnings without issue.

## Recent Implementations (v2 Extensions)

As of the latest iteration, the following features have been successfully implemented using this extended architecture:

1. **Universal Post Object (`ExtendedEntry`)**: A normalization layer that converts ActivityPub, Twtxt, and RSS/Atom into the standard JF2 schema, whilst safely tucking native extensions (Polls, CWs) into `_indieinabox`.
2. **Asynchronous Webmention Discovery**: To comply with the offline-first/non-blocking UI policy, Webmention endpoint discovery is offloaded to the `BackgroundWorker`. The UI reads from a `webmention_discovery_cache` table to instantly render native `like` or `local_like` buttons.
3. **Native Polls & Content Warnings**: The custom UI intercepts the `_indieinabox` metadata. It injects CSS to hide the standard HTML fallbacks and renders interactive progress bars for polls and CSS-blurred overlays for content warnings.
4. **ActivityPub Poll Voting**: Clicking a native poll option triggers an asynchronous Microsub `interact` call (`action=poll_vote`), which generates an ActivityPub `Create -> Note` payload mimicking Mastodon's poll vote format.
5. **Hashtag Parser**: Automatic extraction of `#hashtags` from raw text content (especially useful for Twtxt) directly into the JF2 `category` array.
