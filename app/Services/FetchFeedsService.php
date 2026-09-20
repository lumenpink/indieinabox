<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use PDO;
use Exception;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Repositories\SqliteSettingsRepository;
use Indieinabox\Support\Yaml;
use Indieinabox\Feeds\Contracts\FeedParserInterface;
use Indieinabox\Feeds\Parsers\TwtxtParser;
use Indieinabox\Feeds\Parsers\RssParser;
use Indieinabox\Feeds\Parsers\AtomParser;
use Indieinabox\Feeds\Parsers\JsonFeedParser;
use Indieinabox\Microsub\ExtendedEntry;
use Indieinabox\Microsub\NormalizationAdapter;

/**
 * Domain service responsible for fetching, parsing, and storing external feed subscriptions.
 */
class FetchFeedsService
{
    private PDO $db;
    private SettingsRepositoryInterface $settings;

    /**
     * @var array<string, FeedParserInterface>
     */
    private array $parsers = [];

    /**
     * @param ?PDO $db
     * @param array<int, FeedParserInterface>|null $parsers
     * @param ?SettingsRepositoryInterface $settings
     */
    public function __construct(
        ?PDO $db = null,
        ?array $parsers = null,
        ?SettingsRepositoryInterface $settings = null
    ) {
        $this->db = $db ?? (Container::getInstance()->has(PDO::class) ? Container::getInstance()->get(PDO::class) : Database::getDb());
        if ($settings !== null) {
            $this->settings = $settings;
        } elseif (Container::getInstance()->has(SettingsRepositoryInterface::class)) {
            $this->settings = Container::getInstance()->get(SettingsRepositoryInterface::class);
        } elseif (class_exists(Database::class) && Database::isConnected()) {
            $this->settings = Database::getSettingsRepository();
        } else {
            $this->settings = new SqliteSettingsRepository($this->db);
        }

        if ($parsers !== null) {
            foreach ($parsers as $parser) {
                $this->addParser($parser);
            }
        } else {
            $this->addParser(new TwtxtParser());
            $this->addParser(new JsonFeedParser());
            $this->addParser(new RssParser());
            $this->addParser(new AtomParser());
        }
    }

    public function addParser(FeedParserInterface $parser): self
    {
        $this->parsers[$parser->getFormat()] = $parser;
        return $this;
    }

    /**
     * @return array<string, FeedParserInterface>
     */
    public function getParsers(): array
    {
        return $this->parsers;
    }

    public function findParser(string $content): ?FeedParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($content)) {
                return $parser;
            }
        }
        return null;
    }

    /**
     * Fetches all registered subscriptions across all channels.
     *
     * @return int Number of subscriptions processed.
     */
    public function fetchAll(): int
    {
        $stmt = $this->db->query('SELECT id, channel_uid, url FROM microsub_subscriptions');
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($subs as $sub) {
            try {
                $this->fetchSubscription($sub['channel_uid'], $sub['url']);
                $count++;
            } catch (Exception $e) {
                error_log("FetchFeedsService: Failed to fetch " . $sub['url'] . " - " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Fetches and parses a single subscription URL.
     *
     * @param string $channel Microsub channel ID (e.g., 'inbox').
     * @param string $url Target subscription URL.
     * @param string|null $rawContent Optional content override for testing or offline parsing.
     * @return int Number of items parsed and saved.
     */
    public function fetchSubscription(string $channel, string $url, ?string $rawContent = null): int
    {
        if ($rawContent === null) {
            $ctx = stream_context_create([
                'http' => [
                    'header' => "Accept: application/activity+json, application/json, application/rss+xml, application/atom+xml, text/html\r\nUser-Agent: Indieinabox-Fetcher\r\n",
                    'timeout' => 10,
                ],
            ]);
            $content = $this->fetchUrl($url, $ctx);
            if ($content === false) {
                throw new Exception("Could not retrieve URL content.");
            }
        } else {
            $content = $rawContent;
        }

        $content = trim($content);
        if ($content === '') {
            return 0;
        }

        // Check if ActivityPub Actor
        if (str_starts_with($content, '{')) {
            $json = json_decode($content, true);
            if (is_array($json) && isset($json['@context']) && in_array('https://www.w3.org/ns/activitystreams', (array)$json['@context'])) {
                return $this->parseActivityPub($channel, $url, $json);
            }
        }

        // Check registered strategy parsers
        $parser = $this->findParser($content);
        if ($parser !== null) {
            $parsedItems = $parser->parse($content, $url);
            $saved = 0;

            foreach ($parsedItems as $item) {
                if ($this->itemExists($item['uid'], $channel)) {
                    continue;
                }

                $processedContent = $this->processHtmlMedia($item['content']);
                $authorName = $item['author']['name'] ?? 'Unknown';

                if ($parser->getFormat() === 'twtxt') {
                    $entry = NormalizationAdapter::fromTwtxt(
                        $item['uid'],
                        $item['url'],
                        $item['content'],
                        $item['published_at'],
                        $authorName
                    );
                } else {
                    $entry = NormalizationAdapter::fromFeed(
                        $item['uid'],
                        $item['url'],
                        $processedContent,
                        $item['published_at'],
                        $authorName,
                        $url
                    );
                }

                if (!empty($item['author']['photo'])) {
                    $entry->author['photo'] = $item['author']['photo'];
                }

                if ($this->saveEntry($entry, $channel, $url)) {
                    $saved++;
                }
            }

            return $saved;
        }

        return 0;
    }

    /**
     * Parses an ActivityPub Actor profile and fetches outbox items.
     *
     * @param string $channel
     * @param string $feedUrl
     * @param array<string, mixed> $json
     * @return int
     */
    private function parseActivityPub(string $channel, string $feedUrl, array $json): int
    {
        $authorName = $json['name'] ?? $json['preferredUsername'] ?? 'Unknown';
        $authorPhoto = $json['icon']['url'] ?? '';
        if ($authorPhoto) {
            $authorPhoto = $this->downloadMedia($authorPhoto, 'image');
        }

        $outboxUrl = $json['outbox'] ?? '';
        if (!$outboxUrl) {
            return 0;
        }

        $ctx = stream_context_create([
            'http' => [
                'header' => "Accept: application/activity+json\r\nUser-Agent: Indieinabox-Fetcher\r\n",
                'timeout' => 10,
            ],
        ]);

        $outboxData = $this->fetchApJson($outboxUrl, $ctx);
        if (!$outboxData) {
            return 0;
        }

        $outbox = json_decode($outboxData, true);
        $saved = 0;

        if (isset($outbox['first'])) {
            $firstUrl = is_string($outbox['first']) ? $outbox['first'] : ($outbox['first']['id'] ?? '');
            if ($firstUrl) {
                $pageData = $this->fetchApJson($firstUrl, $ctx);
                if ($pageData) {
                    $page = json_decode($pageData, true);
                    $items = $page['orderedItems'] ?? $page['items'] ?? [];
                    foreach ($items as $item) {
                        $obj = is_string($item) ? null : ($item['object'] ?? $item);
                        if (!is_array($obj)) {
                            continue;
                        }

                        $id = $obj['id'] ?? md5(json_encode($obj));
                        if ($this->itemExists((string)$id, $channel)) {
                            continue;
                        }

                        $contentHtml = $obj['content'] ?? $obj['summary'] ?? '';
                        $contentHtml = $this->processHtmlMedia($contentHtml);

                        $entry = NormalizationAdapter::fromActivityPub($obj, $contentHtml);
                        $entry->author = [
                            'type' => 'card',
                            'name' => $authorName,
                            'photo' => $authorPhoto,
                        ];

                        if ($this->saveEntry($entry, $channel, $feedUrl)) {
                            $saved++;
                        }
                    }
                }
            }
        }

        return $saved;
    }

    public function itemExists(string $id, string $channel): bool
    {
        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . '/data');
        $channelDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $channel);
        $filepath = $channelDir . DIRECTORY_SEPARATOR . md5($id) . '.md';
        return file_exists($filepath);
    }

    public function saveEntry(ExtendedEntry $entry, string $channel, string $feedUrl = ''): bool
    {
        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . '/data');
        $channelDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $channel);
        if (!is_dir($channelDir)) {
            @mkdir($channelDir, 0755, true);
        }

        $filename = md5($entry->uid) . '.md';
        $filepath = $channelDir . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($filepath)) {
            return false;
        }

        $frontmatter = [
            'id' => $entry->uid,
            'url' => $entry->url,
            'feed_url' => $feedUrl,
            'author_name' => $entry->author['name'] ?? 'Unknown',
            'author_photo' => $entry->author['photo'] ?? '',
            'published' => strtotime($entry->published) ?: time(),
            'is_read' => 0,
            'type' => 'feed',
            '_indieinabox' => [
                'network' => $entry->network,
                'origin_server' => $entry->originServer,
                'capabilities' => $entry->capabilities,
                'content_warning' => $entry->contentWarning,
                'poll' => $entry->poll,
                'reels' => $entry->reels,
            ],
        ];

        $yaml = new Yaml();
        $yamlStr = $yaml->dump($frontmatter);
        $content = $entry->content['html'] ?: $entry->content['text'];
        $fileContent = "---\n" . $yamlStr . "---\n\n" . $content;

        return file_put_contents($filepath, $fileContent) !== false;
    }

    public function processHtmlMedia(string $html): string|null
    {
        return preg_replace_callback('/<(img|video|audio|source)[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', function ($matches) {
            $fullTag = $matches[0];
            $tagType = strtolower($matches[1]);
            $url = $matches[2];

            $type = 'image';
            if ($tagType === 'video' || $tagType === 'source') {
                $type = 'video';
            }
            if ($tagType === 'audio') {
                $type = 'audio';
            }

            $localUrl = $this->downloadMedia($url, $type);
            return str_replace($url, $localUrl, $fullTag);
        }, $html);
    }

    public function downloadMedia(string $url, string $type): string
    {
        if (!$url || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $url;
        }

        $fqdn = rtrim((string) ($this->settings->get('fqdn') ?? ''), '/');
        if (str_starts_with($url, '/media/') || ($fqdn && str_starts_with($url, $fqdn))) {
            return $url;
        }

        $enabledStr = $this->settings->get("download_media_{$type}");
        $enabled = $enabledStr === null || $enabledStr === '' || $enabledStr === '1' || $enabledStr === 'true';
        if (!$enabled) {
            return $url;
        }

        $maxSizeStr = $this->settings->get("download_media_max_size_mb");
        $maxSizeMB = $maxSizeStr !== null && $maxSizeStr !== '' ? (float)$maxSizeStr : 10.0;
        $maxSizeBytes = $maxSizeMB * 1024.0 * 1024.0;

        $baseDir = dirname(__DIR__, 2) . '/data/microsub/media';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }

        $parsedPath = parse_url($url, PHP_URL_PATH);
        $ext = $parsedPath ? pathinfo($parsedPath, PATHINFO_EXTENSION) : '';
        if (!$ext || strlen($ext) > 5) {
            $ext = $type === 'image' ? 'jpg' : ($type === 'video' ? 'mp4' : 'mp3');
        }

        $filename = md5($url) . '.' . $ext;
        $filepath = $baseDir . '/' . $filename;
        $localUrl = '/media/microsub/' . $filename;

        if (file_exists($filepath)) {
            return $localUrl;
        }

        $ch = curl_init($url);
        $fp = @fopen($filepath, 'wb');
        if (!$fp) {
            return $url;
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($maxSizeBytes > 0) {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($ch, $download_size, $downloaded, $upload_size, $uploaded) use ($maxSizeBytes) {
                if ($maxSizeBytes > 0 && ($download_size > $maxSizeBytes || $downloaded > $maxSizeBytes)) {
                    return 1;
                }
                return 0;
            });
        }

        curl_setopt($ch, CURLOPT_USERAGENT, 'IndieInABox Fetcher Bot');
        $result = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);
        fclose($fp);

        if ($result === false || $error) {
            @unlink($filepath);
            return $url;
        }

        return $localUrl;
    }

    /**
     * @param null|resource $context
     *
     * @return false|string
     */
    protected function fetchUrl(string $url, $context = null): string|false
    {
        return @file_get_contents($url, false, $context);
    }

    /**
     * @param null|resource $fallbackCtx
     *
     * @return false|string
     */
    private function fetchApJson(string $url, $fallbackCtx = null): string|false
    {
        $stmt = $this->db->query("SELECT private_key FROM activitypub_keys WHERE key_id = 'main-key'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if ($row && !empty($row['private_key']) && class_exists('\Indieinabox\Federation\HttpSignature')) {
            $privateKey = $row['private_key'];
            $fqdn = $this->settings->get('fqdn');

            if ($fqdn) {
                $fqdn = rtrim($fqdn, '/');
                $keyId = $fqdn . '/actor#main-key';

                $sigHeaders = \Indieinabox\Federation\HttpSignature::sign(
                    $keyId,
                    $privateKey,
                    'GET',
                    $url,
                    '',
                    ['Accept' => 'application/activity+json']
                );

                $headersList = [
                    "Accept: application/activity+json",
                    "User-Agent: Indieinabox-Fetcher",
                ];

                foreach ($sigHeaders as $k => $v) {
                    $headersList[] = "$k: $v";
                }

                $ctx = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => implode("\r\n", $headersList) . "\r\n",
                    ],
                ]);

                $data = $this->fetchUrl($url, $ctx);
                if ($data) {
                    return $data;
                }
            }
        }

        return $this->fetchUrl($url, $fallbackCtx);
    }
}
