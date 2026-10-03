<?php

declare(strict_types=1);

namespace Indieinabox\Search;

use PDO;
use Indieinabox\Core\Database;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Search\Contracts\SearchServiceInterface;
use Indieinabox\Site\Site;
use Indieinabox\Support\TextParser;
use Indieinabox\Support\Yaml;

/**
 * Class SearchService
 *
 * Implements SQLite FTS5 full-text search indexing and querying for
 * markdown posts, digital garden notes, and ingested Microsub/Twtxt feeds.
 */
class SearchService implements SearchServiceInterface
{
    private PDO $db;
    private ?Site $site;

    public function __construct(?PDO $db = null, ?Site $site = null)
    {
        $this->db = $db ?? Database::getDb();
        $this->site = $site;
        $this->ensureTable();
    }

    /**
     * Ensures the FTS5 virtual table exists in the database.
     */
    public function ensureTable(): void
    {
        try {
            $this->db->exec('CREATE VIRTUAL TABLE IF NOT EXISTS search_index USING fts5(
                doc_id UNINDEXED,
                doc_type,
                title,
                content,
                tags,
                url UNINDEXED,
                published_at UNINDEXED,
                author UNINDEXED,
                lang UNINDEXED,
                tokenize=\'porter unicode61 remove_diacritics 2\'
            )');
        } catch (\Throwable $e) {
            // Ignore if already created or table setup handled
        }
    }

    /**
     * Sanitizes user input into a safe and robust FTS5 query string.
     * Supports prefix search and multi-lingual character handling.
     */
    public static function sanitizeQuery(string $query): string
    {
        $cleaned = trim($query);
        if ($cleaned === '') {
            return '';
        }

        // If user already specified explicit balanced quotes, keep phrases
        if (preg_match_all('/"([^"]+)"/', $cleaned, $matches) && count($matches[1]) > 0) {
            $terms = [];
            foreach ($matches[1] as $phrase) {
                $phraseCleaned = preg_replace('/[^\p{L}\p{N}\s_-]/u', ' ', $phrase);
                $phraseCleaned = trim((string) preg_replace('/\s+/', ' ', (string) $phraseCleaned));
                if ($phraseCleaned !== '') {
                    $terms[] = '"' . $phraseCleaned . '"';
                }
            }
            $remaining = trim((string) preg_replace('/"([^"]+)"/', ' ', $cleaned));
            if ($remaining !== '') {
                $words = array_values(array_filter(preg_split('/\s+/u', $remaining) ?: [], fn($w) => trim($w) !== ''));
                foreach ($words as $i => $word) {
                    $wClean = preg_replace('/[^\p{L}\p{N}_-]/u', '', $word);
                    if ($wClean === '') continue;
                    $terms[] = ($i === count($words) - 1 && mb_strlen($wClean) >= 2) ? '"' . $wClean . '"*' : '"' . $wClean . '"';
                }
            }
            return implode(' AND ', $terms);
        }

        // Default clean word-by-word tokenizer
        $cleaned = preg_replace('/[^\p{L}\p{N}\s_-]/u', ' ', $cleaned);
        $words = array_values(array_filter(preg_split('/\s+/u', (string) $cleaned) ?: [], fn($w) => trim($w) !== ''));
        if (empty($words)) {
            return '';
        }

        $terms = [];
        foreach ($words as $i => $word) {
            $word = trim($word);
            if ($word === '') continue;
            if ($i === count($words) - 1 && mb_strlen($word) >= 2) {
                $terms[] = '"' . $word . '"*';
            } else {
                $terms[] = '"' . $word . '"';
            }
        }

        return implode(' AND ', $terms);
    }

    /**
     * Cleans raw Markdown and HTML into plain text for indexing.
     */
    public static function extractPlainText(string $raw): string
    {
        // Strip markdown code fences
        $clean = preg_replace('/```[\s\S]*?```/', ' ', $raw);
        // Strip HTML tags
        $clean = strip_tags((string) $clean);
        // Strip Markdown links [text](url) -> text
        $clean = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', (string) $clean);
        // Strip Obsidian wikilinks [[link|text]] -> text, [[link]] -> link
        $clean = preg_replace('/\[\[[^|\]]+\|([^\]]+)\]\]/', '$1', (string) $clean);
        $clean = preg_replace('/\[\[([^\]]+)\]\]/', '$1', (string) $clean);
        // Collapse whitespace
        $clean = preg_replace('/\s+/', ' ', (string) $clean);

        return trim((string) $clean);
    }

    /**
     * Indexes a single generic document into FTS5.
     */
    public function indexDocument(
        string $docId,
        string $docType,
        string $title,
        string $content,
        string $tags = '',
        string $url = '',
        string $publishedAt = '',
        string $author = '',
        string $lang = 'en'
    ): bool {
        $this->deleteDocument($docId);

        $stmt = $this->db->prepare('INSERT INTO search_index (
            doc_id, doc_type, title, content, tags, url, published_at, author, lang
        ) VALUES (
            :doc_id, :doc_type, :title, :content, :tags, :url, :published_at, :author, :lang
        )');

        return $stmt->execute([
            ':doc_id' => $docId,
            ':doc_type' => $docType,
            ':title' => $title,
            ':content' => $content,
            ':tags' => $tags,
            ':url' => $url,
            ':published_at' => $publishedAt,
            ':author' => $author,
            ':lang' => $lang,
        ]);
    }

    /**
     * Removes a document from the FTS5 index.
     */
    public function deleteDocument(string $docId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM search_index WHERE doc_id = :doc_id');
        return $stmt->execute([':doc_id' => $docId]);
    }

    /**
     * Indexes a domain Page (article, note, garden note, photo, etc.).
     */
    public function indexPage(Page $page): bool
    {
        if (($page->filepath && basename($page->filepath) === 'intro.md') || in_array('draft', $page->metadata->tags ?? [])) {
            return false;
        }

        $slug = (string) $page->slug;
        if (in_array($page->kind, ['generic', 'home', 'page'], true) && ($slug === '/' || $slug === 'index/' || $slug === '')) {
            return false;
        }

        $lang = (string) ($page->lang ?? ($this->site->localization->defaultLang ?? 'en'));
        $title = (string) ($page->metadata->title ?? $page->title ?? 'Untitled');
        $kind = (string) ($page->kind ?? 'post');
        $docType = ($kind === 'garden' || str_contains($slug, 'garden/')) ? 'garden' : $kind;
        $docId = "post:{$slug}";

        // Collect tags
        $tags = $page->metadata->tags ?? [];
        if (!is_array($tags)) {
            $tags = [$tags];
        }
        $tags = array_values(array_filter($tags, fn($t) => is_string($t) && trim($t) !== '' && $t !== 'No Tag'));

        $rawBody = (string) ($page->rawBody ?? ($page->content->rawBody ?? ''));
        if ($rawBody === '' && isset($page->content->content)) {
            $rawBody = (string) $page->content->content;
        }

        $extracted = TextParser::extractHashtags($rawBody);
        if (!empty($extracted)) {
            $tags = array_values(array_unique(array_merge($tags, $extracted)));
        }

        $plainContent = self::extractPlainText($rawBody);
        $tagsString = implode(' ', $tags);
        $url = '/' . ltrim($slug, '/');
        if (!str_ends_with($url, '/') && !str_contains(basename($url), '.')) {
            $url .= '/';
        }

        $dateIso = $page->date instanceof \DateTimeInterface
            ? $page->date->format('c')
            : (is_numeric($page->date) ? date('c', (int) $page->date) : date('c'));

        $author = (string) ($page->metadata->author ?? ($this->site->author ?? 'Author'));

        return $this->indexDocument(
            docId: $docId,
            docType: $docType,
            title: $title,
            content: $plainContent,
            tags: $tagsString,
            url: $url,
            publishedAt: $dateIso,
            author: $author,
            lang: $lang
        );
    }

    /**
     * Bulk indexes all pages in the Pages collection.
     */
    public function indexAllPages(Pages $pages): int
    {
        $count = 0;
        foreach ($pages as $page) {
            if ($this->indexPage($page)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Indexes an ingested Microsub / Twtxt feed item.
     *
     * @param array<string, mixed> $entry
     */
    public function indexFeedEntry(array $entry, string $channel = 'inbox'): bool
    {
        $uid = (string) ($entry['uid'] ?? $entry['_id'] ?? uniqid());
        $docId = "feed:{$channel}:{$uid}";
        $docType = 'feed';

        $authorName = (string) ($entry['author']['name'] ?? ($entry['author_name'] ?? 'Unknown'));
        $url = (string) ($entry['url'] ?? '');
        $date = (string) ($entry['published'] ?? date('c'));

        $htmlOrText = (string) ($entry['content']['html'] ?? $entry['content']['text'] ?? $entry['summary'] ?? '');
        $plainContent = self::extractPlainText($htmlOrText);

        $title = (string) ($entry['name'] ?? '');
        if ($title === '') {
            $title = mb_substr($plainContent, 0, 70);
            if (mb_strlen($plainContent) > 70) {
                $title .= '...';
            }
            if ($title === '') {
                $title = "Feed from {$authorName}";
            }
        }

        $tags = [];
        if (!empty($entry['category']) && is_array($entry['category'])) {
            $tags = $entry['category'];
        }
        $extracted = TextParser::extractHashtags($plainContent);
        if (!empty($extracted)) {
            $tags = array_values(array_unique(array_merge($tags, $extracted)));
        }
        $tagsString = implode(' ', $tags);

        $lang = (string) ($entry['lang'] ?? 'en');

        return $this->indexDocument(
            docId: $docId,
            docType: $docType,
            title: $title,
            content: $plainContent,
            tags: $tagsString,
            url: $url,
            publishedAt: $date,
            author: $authorName,
            lang: $lang
        );
    }

    /**
     * Scans and indexes all feed items in data/microsub/inbox/.
     */
    public function indexAllFeeds(): int
    {
        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . '/data');
        $inboxDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox';

        if (!is_dir($inboxDir)) {
            return 0;
        }

        $count = 0;
        $channels = (array) glob($inboxDir . '/*', GLOB_ONLYDIR);
        foreach ($channels as $channelDir) {
            $channel = basename((string) $channelDir);
            $files = (array) glob($channelDir . '/*.md');
            foreach ($files as $file) {
                if (!is_file((string) $file)) continue;

                $content = (string) file_get_contents((string) $file);
                $fm = [];
                $body = $content;

                if (preg_match('/^---\r?\n([\s\S]*?)\r?\n---\r?\n([\s\S]*)$/', $content, $matches)) {
                    $yamlParser = new Yaml();
                    $fm = $yamlParser->loadString($matches[1]) ?: [];
                    $body = $matches[2];
                }

                $entry = [
                    'uid' => $fm['id'] ?? basename((string) $file, '.md'),
                    'url' => $fm['url'] ?? '',
                    'published' => isset($fm['published']) ? (is_numeric($fm['published']) ? date('c', (int)$fm['published']) : $fm['published']) : date('c'),
                    'name' => $fm['title'] ?? $fm['name'] ?? null,
                    'author' => [
                        'name' => $fm['author_name'] ?? 'Unknown',
                        'photo' => $fm['author_photo'] ?? '',
                    ],
                    'content' => [
                        'text' => $body,
                        'html' => $body,
                    ],
                ];

                if ($this->indexFeedEntry($entry, $channel)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Rebuilds the search index from scratch for both posts and feeds.
     *
     * @return array{posts: int, feeds: int}
     */
    public function rebuildIndex(Pages $pages): array
    {
        $this->ensureTable();
        $this->db->exec('DELETE FROM search_index;');

        $postsCount = $this->indexAllPages($pages);
        $feedsCount = $this->indexAllFeeds();

        return [
            'posts' => $postsCount,
            'feeds' => $feedsCount,
        ];
    }

    /**
     * Performs a full-text search query using SQLite FTS5 ranking and snippet extraction.
     *
     * @param array<string, mixed> $options Filter options: 'type', 'lang', 'limit', 'offset'.
     * @return array{query: string, total: int, limit: int, offset: int, results: array<int, array<string, mixed>>}
     */
    public function search(string $query, array $options = []): array
    {
        $this->ensureTable();

        $limit = isset($options['limit']) ? max(1, min(100, (int) $options['limit'])) : 20;
        $offset = isset($options['offset']) ? max(0, (int) $options['offset']) : 0;
        $typeFilter = !empty($options['type']) ? trim((string) $options['type']) : null;
        $langFilter = !empty($options['lang']) ? trim((string) $options['lang']) : null;

        $sanitized = self::sanitizeQuery($query);
        if ($sanitized === '') {
            return [
                'query' => $query,
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'results' => [],
            ];
        }

        $whereClauses = ['search_index MATCH :query'];
        $params = [':query' => $sanitized];

        if ($typeFilter !== null && $typeFilter !== '*' && $typeFilter !== 'all') {
            $whereClauses[] = 'doc_type = :doc_type';
            $params[':doc_type'] = $typeFilter;
        }

        if ($langFilter !== null && $langFilter !== '*' && $langFilter !== 'all') {
            $whereClauses[] = 'lang = :lang';
            $params[':lang'] = $langFilter;
        }

        $whereSql = implode(' AND ', $whereClauses);

        // Count total hits
        $countStmt = $this->db->prepare("SELECT count(*) FROM search_index WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        if ($total === 0) {
            return [
                'query' => $query,
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'results' => [],
            ];
        }

        // Fetch ranked results with BM25 weights (title: 5.0, content: 1.0, tags: 2.0)
        $querySql = "SELECT 
            doc_id,
            doc_type,
            title,
            tags,
            url,
            published_at,
            author,
            lang,
            snippet(search_index, 3, '<mark>', '</mark>', '...', 25) AS snippet,
            bm25(search_index, 5.0, 1.0, 2.0) AS rank
        FROM search_index
        WHERE {$whereSql}
        ORDER BY rank ASC
        LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($querySql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $results = [];

        foreach ($rows as $row) {
            $tagsList = array_values(array_filter(explode(' ', (string) ($row['tags'] ?? '')), fn($t) => trim($t) !== ''));

            $snippet = (string) ($row['snippet'] ?? '');
            if ($snippet === '') {
                $snippet = mb_substr((string) ($row['title'] ?? ''), 0, 120);
            }

            $results[] = [
                'doc_id' => (string) $row['doc_id'],
                'type' => (string) $row['doc_type'],
                'title' => (string) $row['title'],
                'snippet' => $snippet,
                'tags' => $tagsList,
                'url' => (string) $row['url'],
                'published_at' => (string) $row['published_at'],
                'author' => (string) $row['author'],
                'lang' => (string) $row['lang'],
                'rank' => (float) $row['rank'],
            ];
        }

        return [
            'query' => $query,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $results,
        ];
    }
}
