# Bluesky & AT Protocol Federation Bridge

IndieInABox provides native support for cross-posting (POSSE - *Publish on Own Site, Syndicate Elsewhere*) to **Bluesky** and the **AT Protocol (Authenticated Transfer Protocol)** network.

---

## 1. Overview & Architecture

The bridge interacts directly with any AT Protocol Personal Data Server (PDS) via standard HTTP XRPC endpoints:
- `com.atproto.server.createSession` & `refreshSession`: Authenticates your handle and app password.
- `com.atproto.repo.createRecord`: Publishes records to `app.bsky.feed.post`.
- `com.atproto.repo.uploadBlob`: Uploads thumbnails and images for rich link cards and photo posts.
- `com.atproto.identity.resolveHandle`: Resolves custom domain handles into DIDs (`did:plc:...` or `did:web:...`).

### Rich Text Facets with Exact UTF-8 Byte Offsets
The AT Protocol Lexicon mandates that link, mention, and hashtag facets (`app.bsky.richtext.facet`) use **raw UTF-8 byte slice indices** (`byteStart` and `byteEnd`), *not* UTF-16 code units or character positions. IndieInABox calculates exact UTF-8 byte positions natively using PHP byte-length measurements and regex capture offsets, correctly preserving boundaries even in multilingual text containing accents and 4-byte emojis.

### External Link Cards (`app.bsky.embed.external`) with Gemini AI Summaries
When publishing long posts or articles:
1. IndieInABox generates an external card embed with the canonical post URL.
2. If Google Gemini is configured (`ai_translation_api_key` or `gemini_api_key`), the system uses `gemini-2.5-flash` to craft a punchy, engaging single-sentence description (<= 150 chars).
3. If Gemini is unavailable, it gracefully extracts and truncates the plain-text article excerpt without breaking words.
4. Article cover images or local photos are automatically uploaded to the PDS as image blobs and attached as the card thumbnail.

---

## 2. Domain Identity Verification

AT Protocol supports verifying your domain as your handle:
- **DNS TXT Record**: `_atproto.yourdomain.com` with value `did=did:plc:...`
- **HTTP Well-Known Fallback**: IndieInABox serves `GET /.well-known/atproto-did` responding with `text/plain` and your DID.

---

## 3. Configuration

In the **Admin Cockpit** (`/admin/config`), navigate to **Tab 04: Social & Federation**:
- **Auto-syndicate**: Check to automatically syndicate new notes and articles upon publication.
- **Handle**: Your custom domain handle (e.g., `lumen.pink`) or standard handle (`username.bsky.social`).
- **App Password**: An App Password generated under *Bluesky Settings > Privacy and Security > App Passwords*.
- **PDS URL**: Defaults to `https://bsky.social`, or specify your custom self-hosted PDS instance.
- **Account DID**: Auto-populated upon connection test or session creation.
- **Test Connection**: Validates authentication with the PDS in real-time.

---

## 4. CLI Usage

The `bsky` command provides complete terminal control:

### Test Authentication
```bash
php indieinabox.php bsky test
```

### Inspect Configured DID
```bash
php indieinabox.php bsky did
```

### Syndicate Existing Post or Note
```bash
php indieinabox.php bsky syndicate 2026-10-03-my-note
php indieinabox.php bsky syndicate content/articles/deep-dive.md --force
```
Upon completion, the canonical Bluesky URL (`https://bsky.app/profile/{handle}/post/{rkey}`) is recorded directly into the Markdown frontmatter:
```yaml
---
title: "Deep Dive into Decentralization"
syndication:
  - "https://bsky.app/profile/lumen.pink/post/3kxyz..."
---
```

### Publish Quick Note
```bash
php indieinabox.php bsky post "Building with AT Protocol from my IndieInABox site! #indieweb"
```
With image:
```bash
php indieinabox.php bsky post "Check out my new setup!" --image=content/media/photo.jpg
```
