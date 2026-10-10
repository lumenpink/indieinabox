# Background Syndication & Instagram Import

IndieInABox includes automated background services executed during each cron worker cycle (`BackgroundWorker::runAll()`):
1. **PESOS Import**: Imports Instagram media from `.meta_inbox/` into native posts (`InstagramImportService`).
2. **POSSE Auto-Syndication**: Automatically broadcasts recent content to decentralized and federated networks (`AutoSyndicationService`).

---

## 1. Instagram Media Import (PESOS)

The `InstagramImportService` enables a Publish Elsewhere, Syndicate to Own Site (PESOS) workflow:
- Scans `content/.meta_inbox/` for incoming Instagram export or mirror folders (`instagram-*`).
- Locates `media_metadata.json` or image/video files and captions (`*.txt`).
- Copies media into `content/97_floranda/fotos/<timestamp>/` or `content/fotos/<timestamp>/`.
- Generates markdown files with appropriate frontmatter metadata (`date`, `title`, `kind: photo`, `instagram_id`).
- Maintains state in `data/.instagram_import_state.json` to avoid duplicate processing.

---

## 2. Auto-Syndication Engine (POSSE)

The `AutoSyndicationService` scans recent posts and syndicates them to external networks (Bluesky AT Protocol, Nostr, etc.):

### Supported Kinds
- `notas` (`note`)
- `fotos` (`photo`)
- `videos` (`video`)
- `artigos` (`article`)

### Syndication Rules & Safety Limits
1. **48-Hour Age Window**: Only files created or dated within the last 48 hours (`now - 48h`) are considered. This prevents burst syndication of legacy archives during rebuilds or bulk imports.
2. **`no-syndicate` Tag**: If `no-syndicate` exists in tags or frontmatter (boolean, tag `#no-syndicate`, or property), the post is completely ignored for syndication, taking precedence over all other settings.
3. **`syndicate-to` Directive**: When specified, limits syndication only to listed targets (e.g. `bluesky`, `nostr`). If omitted, all enabled channels are syndicated.
4. **Provenance Tracking**: Upon successful syndication, the frontmatter is updated with the respective syndication URLs or note IDs (`syndication:` or `bsky:`, `nostr:`), ensuring idempotency and preventing duplicate broadcasts.

---

## 3. Worker Execution Flow

Within `BackgroundWorker::runAll()`:
1. `processInstagramImport()` – Ingests new Instagram posts into local markdown and media.
2. `processAutoSyndication()` – Syndicates newly imported or written posts to Bluesky and Nostr.
3. `processInboxQueue()` – Handles incoming Webmentions, ActivityPub activities, and site rebuild triggers.
4. `processOutbox()` – Delivers queued ActivityPub messages to followers.
5. Feeds, backups, and updates are processed sequentially under lock protection (`cron.lock`).
