# SQLite FTS5 Full-Text Search

IndieInABox includes a native, self-contained full-text search engine powered by **SQLite FTS5**. It indexes Markdown posts, digital garden notes, taxonomies, and ingested Microsub/Twtxt feed entries without requiring any external dependencies, daemon processes, or cloud services.

---

## ⚡ Key Highlights

* **100% Local-First & Self-Contained**: Operates directly inside the site's SQLite database (`indieinabox.sqlite`).
* **Multilingual Tokenizer with Diacritic Normalization**: Configured with `porter unicode61 remove_diacritics 2`, meaning queries like `saude` match accented words like `saúde` or `Saúde Coletiva` seamlessly across Portuguese, Spanish, and English.
* **Weighted Relevance (BM25)**: Ranks results with higher priority for matches in post titles (`5.0`), followed by tags (`2.0`) and full body text (`1.0`).
* **Instant Snippet Extraction**: Automatically extracts highlighted previews (`<mark>...</mark>`) around matching words.
* **Multi-Channel Interface**: Search directly via CLI terminal, web UI, or RESTful JSON API.

---

## 🗄️ Database Schema

The virtual table `search_index` is automatically created on first database connection:

```sql
CREATE VIRTUAL TABLE IF NOT EXISTS search_index USING fts5(
    doc_id UNINDEXED,         -- Unique identifier, e.g. "post:artigos/saude", "feed:inbox/uuid"
    doc_type,                 -- Content type: 'post', 'garden', 'feed', 'article', 'note'
    title,                    -- Document title or subject
    content,                  -- Plain text content (Markdown and HTML stripped)
    tags,                     -- Normalized space-separated tags and categories
    url UNINDEXED,            -- Canonical or relative URL (/artigos/saude/)
    published_at UNINDEXED,   -- ISO8601 publication timestamp
    author UNINDEXED,         -- Author display name or feed source
    lang UNINDEXED,           -- Language locale code ('pt', 'en', 'es')
    tokenize='porter unicode61 remove_diacritics 2'
);
```

---

## 🔄 Automatic Index Synchronization

1. **Static Site Build (`php indieinabox.php build`)**:
   - `IndexPublisher::publishSearchIndex()` scans and indexes all markdown articles, notes, and digital garden pages into `search_index` alongside generating static `search.json` files.
2. **Feed Fetching (`FetchFeedsService`)**:
   - Every newly fetched RSS, Twtxt, or Microsub entry saved in `data/microsub/inbox/` is automatically indexed into FTS5 in real time.

---

## 💻 CLI Terminal Usage

### Basic Query
```bash
php indieinabox.php search "saúde coletiva"
```

### Filter by Content Type
```bash
# Search only digital garden notes
php indieinabox.php search "neuropsicologia" --type=garden

# Search only ingested feeds in Microsub reader
php indieinabox.php search "indieweb" --type=feed

# Search only articles
php indieinabox.php search "cibernética" --type=article
```

### Full Reindex
To rebuild the entire search index from scratch across all Markdown files and feed archives:
```bash
php indieinabox.php search --reindex
```

### JSON Output (Pipelines & Scripts)
Pass `--json` to output clean JSON suitable for piping to `jq` or external tools:
```bash
php indieinabox.php search "parsers" --json
```

---

## 🌐 HTTP Web & JSON API Endpoints

IndieInABox exposes full-text search directly through its built-in web server:

### HTML Web Interface (`/search`)
Navigating to `/search?q=meu+termo` renders a high-contrast (WCAG AAA) search page with interactive filter pills (`🔍 Tudo`, `📝 Posts`, `🌱 Garden`, `📡 Feeds`), result counts, and highlighted snippets.

### JSON API (`/search` or `/api/search`)
Requests with `format=json`, header `Accept: application/json`, or matching `/api/search` return structured JSON payloads:

```http
GET /api/search?q=parsers&limit=5 HTTP/1.1
Host: localhost:8080
```

```json
{
    "query": "parsers",
    "total": 3,
    "limit": 5,
    "offset": 0,
    "results": [
        {
            "doc_id": "feed:inbox:https://inessential.com/...",
            "type": "feed",
            "title": "Xcoders Justin Miller presentation",
            "snippet": "...writing <mark>parsers</mark>, and I’m very curious...",
            "tags": ["swift", "parsers"],
            "url": "https://inessential.com/...",
            "published_at": "2026-06-04T16:23:00+00:00",
            "author": "inessential.com",
            "lang": "en",
            "rank": -4.079
        }
    ]
}
```
