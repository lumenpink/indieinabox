<?php

declare(strict_types=1);

namespace Indieinabox\AtProto;

use DateTimeImmutable;
use Indieinabox\AtProto\Contracts\AtProtoClientInterface;
use Indieinabox\Core\Database;
use Indieinabox\Page\Page;
use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Site\Site;
use RuntimeException;
use Throwable;

/**
 * Class BskySyndicationService
 *
 * Orchestrates cross-posting / syndication of IndieInABox content to the Bluesky AT Protocol network.
 * Handles sessions, rich text facets, card previews via Gemini, and POSSE syndication frontmatter recording.
 */
class BskySyndicationService
{
    private ?Site $site;
    private AtProtoClientInterface $client;
    private ?AtProtoCardBuilder $cardBuilder;
    private ?array $activeSession = null;

    public function __construct(
        ?Site $site = null,
        ?AtProtoClientInterface $client = null,
        ?AtProtoCardBuilder $cardBuilder = null
    ) {
        $this->site = $site;
        $this->client = $client ?? new AtProtoClient();
        $this->cardBuilder = $cardBuilder;
    }

    public function getClient(): AtProtoClientInterface
    {
        return $this->client;
    }

    public function getCardBuilder(): AtProtoCardBuilder
    {
        if ($this->cardBuilder === null) {
            $apiKey = (string) (Database::getSetting('ai_translation_api_key') ?? Database::getSetting('gemini_api_key') ?? '');
            $model = (string) (Database::getSetting('ai_translation_model') ?: 'gemini-3.5-flash');
            $this->cardBuilder = new AtProtoCardBuilder($apiKey !== '' ? $apiKey : null, $model);
        }
        return $this->cardBuilder;
    }

    /**
     * Retrieves saved ATProto configuration values.
     *
     * @return array{handle: string, password: string, pdsUrl: string, did: string, autoSyndicate: bool}
     */
    public function getConfig(): array
    {
        $handle = (string) (Database::getSetting('atproto_handle') ?? '');
        $password = (string) (Database::getSetting('atproto_app_password') ?? '');
        $pdsUrl = (string) (Database::getSetting('atproto_pds_url') ?: AtProtoClient::DEFAULT_PDS_URL);
        $did = (string) (Database::getSetting('atproto_did') ?? '');
        $auto = (Database::getSetting('atproto_auto_syndicate') === '1');

        return [
            'handle' => trim($handle),
            'password' => trim($password),
            'pdsUrl' => trim($pdsUrl) ?: AtProtoClient::DEFAULT_PDS_URL,
            'did' => trim($did),
            'autoSyndicate' => $auto,
        ];
    }

    /**
     * Checks if ATProto syndication is configured and ready.
     */
    public function isConfigured(): bool
    {
        $config = $this->getConfig();
        return $config['handle'] !== '' && $config['password'] !== '';
    }

    /**
     * Tests credentials by initiating a session.
     *
     * @return array{success: bool, did?: string, handle?: string, pdsUrl?: string, error?: string}
     */
    public function testCredentials(?string $handle = null, ?string $password = null, ?string $pdsUrl = null): array
    {
        $cfg = $this->getConfig();
        $h = $handle !== null && $handle !== '' ? trim($handle) : $cfg['handle'];
        $p = $password !== null && $password !== '' ? trim($password) : $cfg['password'];
        $u = $pdsUrl !== null && $pdsUrl !== '' ? trim($pdsUrl) : $cfg['pdsUrl'];

        if ($h === '' || $p === '') {
            return [
                'success' => false,
                'error' => 'Handle and App Password are required.',
            ];
        }

        try {
            $session = $this->client->createSession($h, $p, $u);
            if (!empty($session['did'])) {
                // Save resolved DID if it wasn't saved or changed
                Database::setSetting('atproto_did', $session['did']);
            }
            return [
                'success' => true,
                'did' => $session['did'],
                'handle' => $session['handle'],
                'pdsUrl' => $session['pdsUrl'],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ensures an authenticated ATProto session is active.
     *
     * @return array{accessJwt: string, refreshJwt: string, did: string, handle: string, pdsUrl: string}
     */
    public function ensureSession(): array
    {
        if ($this->activeSession !== null) {
            return $this->activeSession;
        }

        $cfg = $this->getConfig();
        if ($cfg['handle'] === '' || $cfg['password'] === '') {
            throw new RuntimeException('ATProto credentials not configured. Please set atproto_handle and atproto_app_password.');
        }

        $session = $this->client->createSession($cfg['handle'], $cfg['password'], $cfg['pdsUrl']);
        if (!empty($session['did']) && $session['did'] !== $cfg['did']) {
            Database::setSetting('atproto_did', $session['did']);
        }

        $this->activeSession = $session;
        return $session;
    }

    /**
     * Syndicates a Page object to Bluesky.
     *
     * @param Page $page
     * @param bool $force Force syndication even if already syndicated
     * @return string|null The canonical Bluesky post URL, or null if skipped
     */
    public function syndicatePage(Page $page, bool $force = false): ?string
    {
        // 1. Check if already syndicated to Bluesky
        $syndications = (array) ($page->metadata->syndication ?? []);
        if (!$force) {
            foreach ($syndications as $s) {
                if (is_string($s) && str_contains($s, 'bsky.app/profile/')) {
                    return $s;
                }
            }
        }

        $canonicalUrl = $this->buildCanonicalPageUrl($page);
        $title = (string) ($page->title ?? '');
        $kind = (string) ($page->kind ?? 'note');
        $rawBody = (string) ($page->rawBody ?? $page->content ?? '');

        // Gather images
        $images = [];
        $pageImages = (array) ($page->images ?? []);
        foreach ($pageImages as $img) {
            if (is_string($img) && $img !== '') {
                $images[] = $this->resolveLocalImagePath($img);
            }
        }
        $images = array_values(array_filter($images));

        $result = $this->syndicatePost(
            $rawBody,
            $kind,
            $title,
            $canonicalUrl,
            $images
        );

        // Record syndication back to frontmatter
        if ($page->filepath && file_exists($page->filepath)) {
            $this->recordSyndicationUrl($page->filepath, $result['url']);
        }

        return $result['url'];
    }

    /**
     * Syndicates a post by its Markdown file path.
     *
     * @param string $filePath
     * @param bool $force
     * @return string|null The canonical Bluesky post URL
     */
    public function syndicateFile(string $filePath, bool $force = false): ?string
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $contentRepo = new FileSystemContentRepository(null, $this->site);
        $raw = (string) file_get_contents($filePath);
        $parsed = $contentRepo->parseFrontmatterMarkdown($raw);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        // Check existing syndication
        $syndications = (array) ($frontmatter['syndication'] ?? []);
        if (!$force) {
            foreach ($syndications as $s) {
                if (is_string($s) && str_contains($s, 'bsky.app/profile/')) {
                    return $s;
                }
            }
        }

        $kind = (string) ($frontmatter['kind'] ?? 'note');
        $title = (string) ($frontmatter['title'] ?? '');
        $slug = basename($filePath, '.md');
        $canonicalUrl = $this->buildCanonicalUrlFromPath($filePath, $slug, $kind);

        // Extract photos if specified in frontmatter
        $images = [];
        if (!empty($frontmatter['photo'])) {
            $photos = is_array($frontmatter['photo']) ? $frontmatter['photo'] : [$frontmatter['photo']];
            foreach ($photos as $ph) {
                if (is_string($ph)) {
                    $images[] = $this->resolveLocalImagePath($ph);
                }
            }
        }
        $images = array_values(array_filter($images));

        // Sync and persist summary with content hash if stale or missing
        $summaryService = new \Indieinabox\Services\AiSummaryService(null, null, $this->site);
        if ($summaryService->syncFile($filePath)) {
            $raw = (string) file_get_contents($filePath);
            $parsed = $contentRepo->parseFrontmatterMarkdown($raw);
            $frontmatter = $parsed['frontmatter'];
            $body = $parsed['body'];
        }
        $customSummary = (string) ($frontmatter['summary'] ?? $frontmatter['description'] ?? '');

        $result = $this->syndicatePost($body, $kind, $title, $canonicalUrl, $images, $customSummary !== '' ? $customSummary : null);

        // Record syndication URL
        $this->recordSyndicationUrl($filePath, $result['url']);

        return $result['url'];
    }

    /**
     * Publishes a raw note or article to Bluesky.
     *
     * @param string $body Markdown or plain text
     * @param string $kind 'note', 'article', etc.
     * @param string $title Post title (if article)
     * @param string|null $canonicalUrl Local post URL
     * @param array<int, string> $localImagePaths Local files to attach
     * @param string|null $customSummary Pre-computed summary
     * @return array{uri: string, cid: string, url: string}
     */
    public function syndicatePost(
        string $body,
        string $kind = 'note',
        string $title = '',
        ?string $canonicalUrl = null,
        array $localImagePaths = [],
        ?string $customSummary = null
    ): array {
        $session = $this->ensureSession();
        $cardBuilder = $this->getCardBuilder();

        $embed = null;
        $postText = '';

        $cleanBody = $cardBuilder->cleanMarkdown($body);
        $isArticle = ($kind === 'article' || ($title !== '' && mb_strlen($cleanBody) > 300));

        if ($isArticle && $canonicalUrl !== null) {
            // Build rich card embed
            $firstImage = $localImagePaths[0] ?? null;
            $embed = $cardBuilder->build(
                $canonicalUrl,
                $title !== '' ? $title : $canonicalUrl,
                $cleanBody,
                $firstImage,
                $this->client,
                $session['accessJwt'],
                $session['pdsUrl'],
                $customSummary
            );

            // Post text
            if ($title !== '') {
                $postText = "{$title}\n\n{$canonicalUrl}";
            } else {
                $excerpt = $cardBuilder->fallbackExcerpt($cleanBody);
                $postText = "{$excerpt}\n\n{$canonicalUrl}";
            }
        } else {
            // Short note or status
            if (mb_strlen($cleanBody) <= 300) {
                $postText = $cleanBody;
            } else {
                // Truncate to fit with canonical URL
                $urlSuffix = $canonicalUrl ? "\n\n{$canonicalUrl}" : '';
                $availableChars = max(50, 300 - mb_strlen($urlSuffix) - 3);
                $truncated = mb_substr($cleanBody, 0, $availableChars);
                $lastSpace = mb_strrpos($truncated, ' ');
                if ($lastSpace !== false && $lastSpace > 30) {
                    $truncated = mb_substr($truncated, 0, $lastSpace);
                }
                $postText = rtrim($truncated) . '...' . $urlSuffix;
            }

            // Image attachments for note
            if (!empty($localImagePaths)) {
                $imageEmbeds = [];
                foreach (array_slice($localImagePaths, 0, 4) as $imgFile) {
                    if (file_exists($imgFile)) {
                        $blob = $this->uploadImageBlob($imgFile, $session);
                        if ($blob !== null) {
                            $imageEmbeds[] = [
                                'alt' => '',
                                'image' => $blob['blob'] ?? $blob,
                            ];
                        }
                    }
                }
                if (!empty($imageEmbeds)) {
                    $embed = [
                        '$type' => 'app.bsky.embed.images',
                        'images' => $imageEmbeds,
                    ];
                }
            }
        }

        // Parse rich text facets (links, hashtags, mentions)
        $facets = AtProtoFacetParser::parse($postText);

        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $postText,
            'createdAt' => (new DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z'),
        ];

        if (!empty($facets)) {
            $record['facets'] = $facets;
        }

        if ($embed !== null) {
            $record['embed'] = $embed;
        }

        $res = $this->client->createRecord(
            $session['did'],
            'app.bsky.feed.post',
            $record,
            $session['accessJwt'],
            $session['pdsUrl']
        );

        $canonicalUrlBsky = AtProtoClient::canonicalPostUrl($res['uri'], $session['handle']);

        return [
            'uri' => $res['uri'],
            'cid' => $res['cid'],
            'url' => $canonicalUrlBsky,
        ];
    }

    /**
     * Uploads an image blob to PDS.
     */
    private function uploadImageBlob(string $filePath, array $session): ?array
    {
        $data = @file_get_contents($filePath);
        if ($data === false || $data === '') {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $filePath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!$mime || !str_starts_with((string) $mime, 'image/')) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => 'image/jpeg',
            };
        }

        return $this->client->uploadBlob($data, (string) $mime, $session['accessJwt'], $session['pdsUrl']);
    }

    /**
     * Appends the Bluesky post URL to the Markdown file's frontmatter `syndication` list.
     */
    public function recordSyndicationUrl(string $filePath, string $syndicationUrl): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $contentRepo = new FileSystemContentRepository(null, $this->site);
        $raw = (string) file_get_contents($filePath);
        $parsed = $contentRepo->parseFrontmatterMarkdown($raw);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        $existing = $frontmatter['syndication'] ?? [];
        if (!is_array($existing)) {
            $existing = [$existing];
        }

        if (!in_array($syndicationUrl, $existing, true)) {
            $existing[] = $syndicationUrl;
            $frontmatter['syndication'] = array_values($existing);

            $newMarkdown = $contentRepo->buildFrontmatterMarkdown($frontmatter, $body);
            return file_put_contents($filePath, $newMarkdown) !== false;
        }

        return true;
    }

    /**
     * Resolves a public or local image path to a local filesystem path.
     */
    private function resolveLocalImagePath(string $path): ?string
    {
        if (file_exists($path)) {
            return $path;
        }

        $dataDir = Database::$dataDir ? dirname(Database::$dataDir) : getcwd();
        $candidates = [
            $dataDir . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . ltrim($path, '/\\'),
            $dataDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . ltrim($path, '/\\'),
            $dataDir . DIRECTORY_SEPARATOR . ltrim($path, '/\\'),
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_file($cand)) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * Builds canonical URL for a Page instance.
     */
    private function buildCanonicalPageUrl(Page $page): string
    {
        $fqdn = rtrim((string) (Database::getSetting('fqdn') ?? $this->site->metadata->fqdn ?? 'https://localhost'), '/');
        $slug = '/' . ltrim((string) ($page->slug ?? 'untitled'), '/');

        if (!str_ends_with($slug, '.html') && !str_ends_with($slug, '/')) {
            $slug .= '.html';
        }

        return $fqdn . $slug;
    }

    /**
     * Builds canonical URL from file path.
     */
    private function buildCanonicalUrlFromPath(string $filePath, string $slug, string $kind): string
    {
        $fqdn = rtrim((string) (Database::getSetting('fqdn') ?? $this->site->metadata->fqdn ?? 'https://localhost'), '/');
        $contentBase = Database::$dataDir ? dirname(Database::$dataDir) . '/content' : 'content';

        $rel = ltrim(str_replace([$contentBase, '\\'], ['', '/'], $filePath), '/');
        $relNoExt = preg_replace('/\.md$/i', '', $rel);

        return $fqdn . '/' . $relNoExt . '.html';
    }
}
