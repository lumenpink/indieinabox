<?php

declare(strict_types=1);

namespace Indieinabox\Discovery;

use PDO;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Discovery\Contracts\DiscoveryServiceInterface;
use Indieinabox\Repositories\Contracts\ContentRepositoryInterface;
use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Services\Contracts\MediaProxyServiceInterface;
use Indieinabox\Services\Contracts\MicrosubFilterServiceInterface;
use Indieinabox\Services\MicrosubFilterService;
use Indieinabox\Site\Site;
use Indieinabox\Support\Yaml;

/**
 * Class DiscoveryService
 *
 * Discovers, filters, and aggregates public timeline items from local publications
 * and federated networks (ActivityPub, Twtxt, RSS/Atom/JSON feeds).
 */
class DiscoveryService implements DiscoveryServiceInterface
{
    private PDO $db;
    private ?Site $site;
    private ?ContentRepositoryInterface $contentRepo;
    private ?MediaProxyServiceInterface $mediaProxy;
    private ?MicrosubFilterServiceInterface $filterService;
    private int $cacheTtl;

    public function __construct(
        ?PDO $db = null,
        ?Site $site = null,
        ?ContentRepositoryInterface $contentRepo = null,
        ?MediaProxyServiceInterface $mediaProxy = null,
        ?MicrosubFilterServiceInterface $filterService = null,
        int $cacheTtl = 60
    ) {
        $this->db = $db ?? Database::getDb();
        $this->site = $site;
        $container = class_exists(Container::class) ? Container::getInstance() : null;

        $this->contentRepo = $contentRepo ?? ($container && $container->has(ContentRepositoryInterface::class)
            ? $container->get(ContentRepositoryInterface::class)
            : new FileSystemContentRepository(null, $site));

        $this->mediaProxy = $mediaProxy ?? ($container && $container->has(MediaProxyServiceInterface::class)
            ? $container->get(MediaProxyServiceInterface::class)
            : null);

        $this->filterService = $filterService ?? ($container && $container->has(MicrosubFilterServiceInterface::class)
            ? $container->get(MicrosubFilterServiceInterface::class)
            : null);

        $this->cacheTtl = $cacheTtl;
        $this->ensureCacheTable();
    }

    private function ensureCacheTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS discovery_cache (' .
            'cache_key TEXT PRIMARY KEY, ' .
            'payload TEXT NOT NULL, ' .
            'expires_at INTEGER NOT NULL)'
        );
    }

    #[\Override]
    public function getTimeline(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $cacheKey = md5(serialize($filters) . "_{$limit}_{$offset}");
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $allItems = $this->collectAllPublicItems();

        // Count distribution by network and tags across all public items before filtering
        $networksCount = [];
        $tagsCount = [];

        foreach ($allItems as $item) {
            $net = $item['network'] ?? 'local';
            $networksCount[$net] = ($networksCount[$net] ?? 0) + 1;

            if (!empty($item['tags']) && is_array($item['tags'])) {
                foreach ($item['tags'] as $t) {
                    $cleanTag = strtolower(ltrim((string)$t, '#'));
                    if ($cleanTag !== '') {
                        $tagsCount[$cleanTag] = ($tagsCount[$cleanTag] ?? 0) + 1;
                    }
                }
            }
        }

        // Apply filters
        $filtered = array_values(array_filter($allItems, function (array $item) use ($filters): bool {
            // Network filter
            if (!empty($filters['network'])) {
                $targetNet = strtolower(trim((string) $filters['network']));
                $itemNet = strtolower(trim((string) ($item['network'] ?? 'local')));
                if ($targetNet === 'activitypub' || $targetNet === 'ap') {
                    if ($itemNet !== 'activitypub' && $itemNet !== 'ap') {
                        return false;
                    }
                } elseif ($itemNet !== $targetNet) {
                    return false;
                }
            }

            // Tag filter
            if (!empty($filters['tag'])) {
                $targetTag = strtolower(ltrim(trim((string) $filters['tag']), '#'));
                $itemTags = array_map(fn ($t) => strtolower(ltrim((string) $t, '#')), $item['tags'] ?? []);
                if (!in_array($targetTag, $itemTags, true)) {
                    // Check if present in content
                    if (!str_contains(strtolower($item['content']['text'] ?? ''), '#' . $targetTag)) {
                        return false;
                    }
                }
            }

            // Search filter
            if (!empty($filters['search'])) {
                $query = strtolower(trim((string) $filters['search']));
                $haystack = strtolower(($item['title'] ?? '') . ' ' . ($item['content']['text'] ?? '') . ' ' . ($item['author']['name'] ?? ''));
                if (!str_contains($haystack, $query)) {
                    return false;
                }
            }

            return true;
        }));

        // Sort descending by publication timestamp
        usort($filtered, fn (array $a, array $b) => ($b['published_ts'] ?? 0) <=> ($a['published_ts'] ?? 0));

        $total = count($filtered);
        $paginatedItems = array_slice($filtered, $offset, $limit);

        // Rewrite media URLs via MediaProxy if configured
        if ($this->mediaProxy !== null) {
            foreach ($paginatedItems as &$pItem) {
                if (!empty($pItem['content']['html'])) {
                    $pItem['content']['html'] = $this->mediaProxy->rewriteHtmlMedia($pItem['content']['html']);
                }
            }
            unset($pItem);
        }

        $result = [
            'items' => $paginatedItems,
            'total' => $total,
            'networks' => $networksCount,
            'tags' => $tagsCount,
        ];

        $this->saveToCache($cacheKey, $result);

        return $result;
    }

    /**
     * Aggregates public items from local Markdown storage and federated Microsub storage.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectAllPublicItems(): array
    {
        $items = [];
        $yaml = new Yaml();

        // 1. Collect Federated items from data/microsub/inbox/
        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . '/data');
        $inboxDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox';

        if (is_dir($inboxDir)) {
            $channelDirs = glob($inboxDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
            foreach ($channelDirs as $cDir) {
                // Notifications channel is inherently private/personal, skip it from public discovery
                if (basename($cDir) === 'notifications') {
                    continue;
                }

                $files = glob($cDir . DIRECTORY_SEPARATOR . '*.md') ?: [];
                foreach ($files as $file) {
                    $raw = file_get_contents($file);
                    if ($raw === false) {
                        continue;
                    }

                    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $matches)) {
                        continue;
                    }

                    $fm = $yaml->loadString($matches[1]);
                    $bodyHtml = trim($matches[2]);

                    // Exclude private or unlisted entries
                    $visibility = strtolower((string) ($fm['visibility'] ?? 'public'));
                    if ($visibility === 'private' || $visibility === 'unlisted' || $visibility === 'direct') {
                        continue;
                    }

                    // Check moderation/mute rules
                    if ($this->filterService !== null) {
                        $action = $this->filterService->evaluate([
                            'name' => (string) ($fm['name'] ?? ($fm['title'] ?? '')),
                            'summary' => (string) ($fm['summary'] ?? ''),
                            'content' => ['html' => $bodyHtml],
                            'author' => [
                                'name' => (string) ($fm['author_name'] ?? ''),
                                'url' => (string) ($fm['author_url'] ?? ''),
                            ],
                            'url' => (string) ($fm['url'] ?? ''),
                            '_indieinabox' => is_array($fm['_indieinabox'] ?? null) ? $fm['_indieinabox'] : [],
                        ], basename($cDir));

                        if ($action === 'mute' || $action === 'drop') {
                            continue;
                        }
                    }

                    $pubInt = (int) ($fm['published'] ?? filemtime($file));
                    $ext = is_array($fm['_indieinabox'] ?? null) ? $fm['_indieinabox'] : [];
                    $net = strtolower((string) ($ext['network'] ?? 'rss'));

                    // Extract hashtags from body if not explicit
                    $tags = is_array($fm['tags'] ?? null) ? $fm['tags'] : (is_array($fm['category'] ?? null) ? $fm['category'] : []);
                    if (empty($tags) && preg_match_all('/#([a-zA-Z0-9_\x{00C0}-\x{00FF}]+)/u', strip_tags($bodyHtml), $tagMatches)) {
                        $tags = array_values(array_unique($tagMatches[1]));
                    }

                    $items[] = [
                        'id' => (string) ($fm['id'] ?? basename($file, '.md')),
                        'type' => 'entry',
                        'network' => $net,
                        'url' => (string) ($fm['url'] ?? ''),
                        'title' => (string) ($fm['name'] ?? ($fm['title'] ?? '')),
                        'published' => date('c', $pubInt),
                        'published_ts' => $pubInt,
                        'author' => [
                            'name' => (string) ($fm['author_name'] ?? 'Anonymous'),
                            'url' => (string) ($fm['author_url'] ?? ''),
                            'photo' => (string) ($fm['author_photo'] ?? ''),
                        ],
                        'content' => [
                            'text' => strip_tags($bodyHtml),
                            'html' => $bodyHtml,
                        ],
                        'tags' => array_values(array_map('strval', $tags)),
                        'photos' => is_array($ext['photos'] ?? null) ? $ext['photos'] : [],
                        'poll' => is_array($ext['poll'] ?? null) ? $ext['poll'] : null,
                        'content_warning' => !empty($ext['content_warning']) ? (string) $ext['content_warning'] : null,
                        'origin_server' => (string) ($ext['origin_server'] ?? ''),
                    ];
                }
            }
        }

        // 2. Collect Local Posts
        $contentBaseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'content';
        if ($this->site !== null && !empty($this->site->paths->contentDir)) {
            $base = rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR);
            $cDir = $this->site->paths->contentDir;
            $contentBaseDir = str_starts_with($cDir, DIRECTORY_SEPARATOR) ? $cDir : $base . DIRECTORY_SEPARATOR . $cDir;
        }

        if (is_dir($contentBaseDir)) {
            $localFiles = $this->scanLocalMarkdownFiles($contentBaseDir);
            $siteFqdn = rtrim($this->site?->metadata->fqdn ?? 'http://localhost', '/');
            $authorName = $this->site?->metadata->author ?? ($this->site?->metadata->sitename ?? 'Local Author');

            foreach ($localFiles as $lFile) {
                $raw = file_get_contents($lFile);
                if ($raw === false || !preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $matches)) {
                    continue;
                }

                $fm = $yaml->loadString($matches[1]);
                $bodyMarkdown = trim($matches[2]);

                $visibility = strtolower((string) ($fm['visibility'] ?? 'public'));
                if ($visibility === 'private' || $visibility === 'unlisted' || $visibility === 'draft') {
                    continue;
                }

                $pubStr = (string) ($fm['date'] ?? ($fm['published'] ?? ''));
                $pubTs = !empty($pubStr) ? strtotime($pubStr) : filemtime($lFile);
                if ($pubTs === false) {
                    $pubTs = filemtime($lFile);
                }

                $slug = (string) ($fm['slug'] ?? basename($lFile, '.md'));
                $url = $siteFqdn . '/' . ltrim($slug, '/');

                $tags = is_array($fm['tags'] ?? null) ? $fm['tags'] : (is_array($fm['category'] ?? null) ? $fm['category'] : []);
                if (empty($tags) && preg_match_all('/#([a-zA-Z0-9_\x{00C0}-\x{00FF}]+)/u', $bodyMarkdown, $tagMatches)) {
                    $tags = array_values(array_unique($tagMatches[1]));
                }

                $items[] = [
                    'id' => 'local_' . md5($lFile),
                    'type' => 'entry',
                    'network' => 'local',
                    'url' => $url,
                    'title' => (string) ($fm['title'] ?? ''),
                    'published' => date('c', $pubTs),
                    'published_ts' => $pubTs,
                    'author' => [
                        'name' => (string) ($fm['author'] ?? $authorName),
                        'url' => $siteFqdn,
                        'photo' => (string) ($this->site?->metadata->avatar ?? ''),
                    ],
                    'content' => [
                        'text' => strip_tags($bodyMarkdown),
                        'html' => nl2br(htmlspecialchars($bodyMarkdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
                    ],
                    'tags' => array_values(array_map('strval', $tags)),
                    'photos' => is_array($fm['photos'] ?? null) ? $fm['photos'] : (!empty($fm['photo']) ? [(string) $fm['photo']] : []),
                    'poll' => is_array($fm['poll'] ?? null) ? $fm['poll'] : null,
                    'content_warning' => !empty($fm['content_warning']) ? (string) $fm['content_warning'] : null,
                    'origin_server' => parse_url($siteFqdn, PHP_URL_HOST) ?? 'localhost',
                ];
            }
        }

        return $items;
    }

    /**
     * Recursively scans for markdown files.
     *
     * @return array<int, string>
     */
    private function scanLocalMarkdownFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'md') {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }

    #[\Override]
    public function clearCache(): void
    {
        $this->ensureCacheTable();
        $this->db->exec('DELETE FROM discovery_cache');
    }

    private function getFromCache(string $key): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT payload FROM discovery_cache WHERE cache_key = :key AND expires_at > :now');
            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
            $stmt->bindValue(':now', time(), PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['payload'])) {
                $decoded = json_decode((string) $row['payload'], true);
                return is_array($decoded) ? $decoded : null;
            }
        } catch (\Throwable) {
            // Ignore cache errors
        }
        return null;
    }

    private function saveToCache(string $key, array $data): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO discovery_cache (cache_key, payload, expires_at) ' .
                'VALUES (:key, :payload, :expires) ' .
                'ON CONFLICT(cache_key) DO UPDATE SET payload = excluded.payload, expires_at = excluded.expires_at'
            );
            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
            $stmt->bindValue(':payload', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmt->bindValue(':expires', time() + $this->cacheTtl, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable) {
            // Ignore cache errors
        }
    }
}
