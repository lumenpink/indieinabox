<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use PDO;
use Exception;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Indieinabox\Core\Database;
use Indieinabox\Site;
use Indieinabox\Support\Yaml;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Microsub\ExtendedEntry;

/**
 * Domain service managing Microsub channels, subscriptions, timeline retrieval, and interactions.
 */
class MicrosubService
{
    private PDO $db;
    private FetchFeedsService $feedFetcher;
    private ?Site $site;

    public function __construct(
        ?PDO $db = null,
        ?FetchFeedsService $feedFetcher = null,
        ?Site $site = null
    ) {
        $this->db = $db ?? Database::getDb();
        $this->feedFetcher = $feedFetcher ?? new FetchFeedsService($this->db);
        $this->site = $site;
    }

    /**
     * Lists all Microsub channels.
     *
     * @return array<int, array{uid: string, name: string}>
     */
    public function getChannels(): array
    {
        $stmt = $this->db->query('SELECT uid, name FROM microsub_channels');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Creates a new Microsub channel.
     *
     * @param string $name Channel display name.
     * @return array{uid: string, name: string}
     */
    public function createChannel(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new Exception("Missing channel name");
        }

        $uid = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name) ?? '');
        if ($uid === '') {
            $uid = 'channel_' . time();
        }

        $stmt = $this->db->prepare('INSERT INTO microsub_channels (uid, name) VALUES (:uid, :name)');
        $stmt->bindValue(':uid', $uid, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->execute();

        return ['uid' => $uid, 'name' => $name];
    }

    /**
     * Deletes a custom channel and its subscriptions.
     * Default channels ('inbox', 'notifications') cannot be deleted.
     */
    public function deleteChannel(string $uid): bool
    {
        $uid = trim($uid);
        if ($uid === '' || $uid === 'inbox' || $uid === 'notifications') {
            throw new Exception("Cannot delete default or empty channel");
        }

        $stmt = $this->db->prepare('DELETE FROM microsub_channels WHERE uid = :uid');
        $stmt->bindValue(':uid', $uid, PDO::PARAM_STR);
        $stmt->execute();

        $stmtSubs = $this->db->prepare('DELETE FROM microsub_subscriptions WHERE channel_uid = :uid');
        $stmtSubs->bindValue(':uid', $uid, PDO::PARAM_STR);
        $stmtSubs->execute();

        return true;
    }

    /**
     * Retrieves the timeline items for a channel.
     *
     * @return array{items: array<int, mixed>, paging?: array{before: ?int, after: ?int}}
     */
    public function getTimeline(string $channel = 'inbox', int $before = 0, int $after = 0, int $limit = 20): array
    {
        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . '/data');
        $channelDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $channel);

        $items = [];
        $firstPub = null;
        $lastPub = null;

        if (is_dir($channelDir)) {
            $files = glob($channelDir . DIRECTORY_SEPARATOR . '*.md');
            if ($files) {
                $parsedItems = [];
                $yamlParser = new Yaml();

                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    if ($content !== false && preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                        $fm = $yamlParser->loadString($matches[1]);
                        $pubInt = (int)($fm['published'] ?? filemtime($file));

                        if ($before > 0 && $pubInt <= $before) {
                            continue;
                        }
                        if ($after > 0 && $pubInt >= $after) {
                            continue;
                        }

                        $parsedItems[] = [
                            'pubInt' => $pubInt,
                            'fm' => $fm,
                            'contentHtml' => trim($matches[2]),
                            'file' => $file,
                        ];
                    }
                }

                usort($parsedItems, fn ($a, $b) => $b['pubInt'] <=> $a['pubInt']);
                $parsedItems = array_slice($parsedItems, 0, $limit);

                foreach ($parsedItems as $p) {
                    $pubInt = $p['pubInt'];
                    if ($firstPub === null) {
                        $firstPub = $pubInt;
                    }
                    $lastPub = $pubInt;

                    $fm = $p['fm'];
                    $entry = new ExtendedEntry();
                    $entry->uid = $fm['id'] ?? basename($p['file'], '.md');
                    $entry->url = $fm['url'] ?? '';
                    $entry->published = date('c', $pubInt);
                    $entry->content['html'] = $p['contentHtml'];
                    $entry->content['text'] = strip_tags($p['contentHtml']);
                    $entry->isRead = (bool)($fm['is_read'] ?? false);

                    if (!empty($fm['author_name'])) {
                        $entry->author = [
                            'type' => 'card',
                            'name' => $fm['author_name'],
                            'photo' => $fm['author_photo'] ?? '',
                        ];
                    }

                    if (!empty($fm['_indieinabox']) && is_array($fm['_indieinabox'])) {
                        $ext = $fm['_indieinabox'];
                        $entry->network = $ext['network'] ?? 'unknown';
                        $entry->originServer = $ext['origin_server'] ?? '';
                        $entry->capabilities = $ext['capabilities'] ?? [];
                        $entry->contentWarning = $ext['content_warning'] ?? null;
                        $entry->poll = $ext['poll'] ?? null;
                        $entry->reels = $ext['reels'] ?? null;
                    }

                    $item = $entry->toJF2Array();
                    $item['_id'] = $entry->uid;
                    $items[] = $item;
                }
            }
        }

        $response = ['items' => $items];
        if (count($items) > 0) {
            $response['paging'] = [
                'before' => $firstPub,
                'after' => $lastPub,
            ];
        }

        return $response;
    }

    /**
     * Marks one or more entries as read.
     *
     * @param string $channel Channel ID.
     * @param array<int, string> $entryIds List of entry UIDs.
     */
    public function markRead(string $channel, array $entryIds): bool
    {
        $channel = preg_replace('/[^a-zA-Z0-9_-]/', '', $channel ?? 'inbox');
        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . '/data');
        $channelDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . $channel;

        $yamlParser = new Yaml();

        foreach ($entryIds as $id) {
            $possibleFiles = [
                $channelDir . DIRECTORY_SEPARATOR . $id . '.md',
                $channelDir . DIRECTORY_SEPARATOR . md5($id) . '.md',
            ];

            foreach ($possibleFiles as $file) {
                if (file_exists($file)) {
                    $content = file_get_contents($file);
                    if ($content !== false && preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                        $fm = $yamlParser->loadString($matches[1]);
                        if (($fm['id'] ?? '') === $id || md5((string)($fm['id'] ?? '')) === md5($id)) {
                            $fm['is_read'] = 1;
                            $yamlStr = $yamlParser->dump($fm);
                            $newContent = "---\n" . $yamlStr . "---\n\n" . trim($matches[2]);
                            file_put_contents($file, $newContent);
                            break;
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * Lists all feed subscriptions for a given channel.
     *
     * @return array<int, array{type: string, url: string, feed_type: string, name: string, photo: string}>
     */
    public function getSubscriptions(string $channel = 'inbox'): array
    {
        $stmt = $this->db->prepare('SELECT url, type, name, photo FROM microsub_subscriptions WHERE channel_uid = :channel');
        $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'type' => 'feed',
                'url' => $row['url'],
                'feed_type' => $row['type'] ?? 'rss',
                'name' => $row['name'] ?? $row['url'],
                'photo' => $row['photo'] ?? '',
            ];
        }

        return $items;
    }

    /**
     * Subscribes to a feed or ActivityPub actor.
     *
     * @return array{type: string, url: string, feed_type: string, name: string}
     */
    public function follow(string $channel, string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            throw new Exception("Missing feed URL");
        }

        $type = 'rss';
        $name = '';
        $photo = '';
        $finalUrl = $url;

        // Handle @user@domain WebFinger lookup
        if (preg_match('/^@?([^@]+)@([^@]+)$/', $url, $matches)) {
            $domain = $matches[2];
            $user = $matches[1];
            $wfUrl = "https://{$domain}/.well-known/webfinger?resource=acct:{$user}@{$domain}";
            $wfCtx = stream_context_create(['http' => ['header' => 'Accept: application/jrd+json', 'timeout' => 5]]);
            $wfData = $this->fetchUrl($wfUrl, $wfCtx);
            if ($wfData) {
                $wfJson = json_decode($wfData, true);
                if (is_array($wfJson) && isset($wfJson['links'])) {
                    foreach ($wfJson['links'] as $link) {
                        if (($link['rel'] ?? '') === 'self' && ($link['type'] ?? '') === 'application/activity+json') {
                            $finalUrl = $link['href'];
                            $type = 'ap';
                            break;
                        }
                    }
                }
            }
        }

        $ctx = stream_context_create([
            'http' => [
                'header' => "Accept: application/activity+json, application/json, application/rss+xml, application/atom+xml, text/html\r\nUser-Agent: Indieinabox/1.0\r\n",
                'timeout' => 5,
            ],
        ]);
        $content = $this->fetchUrl($finalUrl, $ctx);
        $inboxUrl = '';

        if ($content) {
            $content = trim($content);
            if (stripos($content, '<html') !== false) {
                if (preg_match_all('/<link\s+[^>]*rel="alternate"[^>]*>/i', $content, $linkMatches)) {
                    $apHref = '';
                    $rssHref = '';
                    $atomHref = '';
                    foreach ($linkMatches[0] as $linkTag) {
                        if (stripos($linkTag, 'type="application/activity+json"') !== false) {
                            if (preg_match('/href="([^"]+)"/i', $linkTag, $hrefM)) {
                                $apHref = $hrefM[1];
                            }
                        } elseif (stripos($linkTag, 'type="application/rss+xml"') !== false) {
                            if (preg_match('/href="([^"]+)"/i', $linkTag, $hrefM)) {
                                $rssHref = $hrefM[1];
                            }
                        } elseif (stripos($linkTag, 'type="application/atom+xml"') !== false) {
                            if (preg_match('/href="([^"]+)"/i', $linkTag, $hrefM)) {
                                $atomHref = $hrefM[1];
                            }
                        }
                    }

                    if ($apHref) {
                        $finalUrl = $apHref;
                        $content = $this->fetchUrl($finalUrl, $ctx);
                        if ($content) {
                            $content = trim($content);
                        }
                    } elseif ($atomHref || $rssHref) {
                        $finalUrl = $atomHref ?: $rssHref;
                        $content = $this->fetchUrl($finalUrl, $ctx);
                        if ($content) {
                            $content = trim($content);
                        }
                    }
                }
            }

            if (str_starts_with($content, '{')) {
                $json = json_decode($content, true);
                if (is_array($json) && isset($json['@context']) && in_array('https://www.w3.org/ns/activitystreams', (array)$json['@context'])) {
                    $type = 'ap';
                    $name = $json['name'] ?? $json['preferredUsername'] ?? '';
                    $photo = $json['icon']['url'] ?? '';
                    $inboxUrl = $json['inbox'] ?? ($json['endpoints']['sharedInbox'] ?? '');
                } elseif (is_array($json) && isset($json['version']) && str_starts_with((string)$json['version'], 'https://jsonfeed.org/version/')) {
                    $type = 'json';
                    $name = $json['title'] ?? '';
                    $photo = $json['icon'] ?? ($json['favicon'] ?? '');
                }
            } elseif (str_starts_with($content, '# nick') || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}/m', $content)) {
                $type = 'twtxt';
                if (preg_match('/^# nick\s*=\s*(.+)$/m', $content, $m)) {
                    $name = trim($m[1]);
                }
            } else {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($content);
                if ($xml !== false) {
                    if (isset($xml->channel)) {
                        $type = 'rss';
                        $name = (string)($xml->channel->title ?? '');
                        $photo = (string)($xml->channel->image->url ?? '');
                    } elseif (isset($xml->entry) || isset($xml->title)) {
                        $type = 'atom';
                        $name = (string)($xml->title ?? '');
                        $photo = (string)($xml->icon ?? ($xml->logo ?? ''));
                    }
                }
            }
        }

        if (!$name) {
            $name = $finalUrl;
        }

        $stmt = $this->db->prepare('INSERT INTO microsub_subscriptions (channel_uid, url, type, name, photo) VALUES (:channel, :url, :type, :name, :photo)');
        $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
        $stmt->bindValue(':url', $finalUrl, PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':photo', $photo, PDO::PARAM_STR);
        $stmt->execute();

        if ($type === 'ap' && !empty($inboxUrl)) {
            $fqdn = rtrim(Database::getSetting('fqdn') ?? 'http://localhost', '/');
            $myActor = $fqdn . '/actor';
            $activityId = $fqdn . '/activity/' . uniqid();
            $payload = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $activityId,
                'type' => 'Follow',
                'actor' => $myActor,
                'object' => $finalUrl,
            ];
            $stmtAp = $this->db->prepare("INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) VALUES (?, ?, 'pending', ?)");
            $stmtAp->execute([json_encode($payload, JSON_UNESCAPED_SLASHES), $inboxUrl, time()]);
        }

        return [
            'type' => 'feed',
            'url' => $finalUrl,
            'feed_type' => $type,
            'name' => $name,
        ];
    }

    /**
     * Unsubscribes from a feed and removes its cached items.
     */
    public function unfollow(string $channel, string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            throw new Exception("Missing feed URL");
        }

        $stmtType = $this->db->prepare('SELECT type FROM microsub_subscriptions WHERE channel_uid = :channel AND url = :url LIMIT 1');
        $stmtType->execute([':channel' => $channel, ':url' => $url]);
        $subType = $stmtType->fetchColumn();

        $stmt = $this->db->prepare('DELETE FROM microsub_subscriptions WHERE channel_uid = :channel AND url = :url');
        $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
        $stmt->bindValue(':url', $url, PDO::PARAM_STR);
        $stmt->execute();

        // Check if remaining channels still follow this url
        $stmtCheck = $this->db->prepare('SELECT COUNT(*) FROM microsub_subscriptions WHERE url = :url');
        $stmtCheck->execute([':url' => $url]);
        $count = (int)$stmtCheck->fetchColumn();

        if ($count === 0 && $subType === 'ap') {
            $ctx = stream_context_create(['http' => ['header' => "Accept: application/activity+json\r\nUser-Agent: Indieinabox/1.0\r\n"]]);
            $actorData = $this->fetchUrl($url, $ctx);
            if ($actorData) {
                $actorObj = json_decode($actorData, true);
                if (is_array($actorObj)) {
                    $inboxUrl = $actorObj['inbox'] ?? ($actorObj['endpoints']['sharedInbox'] ?? '');
                    if ($inboxUrl) {
                        $fqdn = rtrim(Database::getSetting('fqdn') ?? 'http://localhost', '/');
                        $myActor = $fqdn . '/actor';
                        $payload = [
                            '@context' => 'https://www.w3.org/ns/activitystreams',
                            'id' => $fqdn . '/activity/' . uniqid(),
                            'type' => 'Undo',
                            'actor' => $myActor,
                            'object' => [
                                'type' => 'Follow',
                                'actor' => $myActor,
                                'object' => $url,
                            ],
                        ];
                        $stmtAp = $this->db->prepare("INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) VALUES (?, ?, 'pending', ?)");
                        $stmtAp->execute([json_encode($payload, JSON_UNESCAPED_SLASHES), $inboxUrl, time()]);
                    }
                }
            }
        }

        // Delete posts for this channel
        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . '/data');
        $channelDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $channel);
        if (is_dir($channelDir)) {
            $files = glob($channelDir . DIRECTORY_SEPARATOR . '*.md');
            $urlDomain = parse_url($url, PHP_URL_HOST);
            if ($files) {
                $processor = new ContentProcessor();
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    if ($content !== false) {
                        $fm = $processor->extractFrontMatter($content);
                        if ($fm) {
                            if (isset($fm['feed_url']) && $fm['feed_url'] === $url) {
                                @unlink($file);
                            } elseif (!isset($fm['feed_url']) && isset($fm['url']) && $urlDomain) {
                                $postDomain = parse_url((string)$fm['url'], PHP_URL_HOST);
                                if ($postDomain === $urlDomain) {
                                    @unlink($file);
                                }
                            }
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * Discovers feeds linked in a target web page.
     *
     * @return array<int, array{type: string, url: string}>
     */
    public function search(string $query): array
    {
        $results = [];
        $query = trim($query);

        if (filter_var($query, FILTER_VALIDATE_URL)) {
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $html = $this->fetchUrl($query, $context);
            if ($html) {
                $dom = new DOMDocument();
                @$dom->loadHTML($html);
                $xpath = new DOMXPath($dom);
                $links = $xpath->query('//link[@rel="alternate"]');
                if ($links) {
                    foreach ($links as $link) {
                        if ($link instanceof DOMElement) {
                            $type = $link->getAttribute('type');
                            $href = $link->getAttribute('href');
                            $allowedTypes = [
                                'application/rss+xml',
                                'application/atom+xml',
                                'application/feed+json',
                                'text/plain',
                            ];
                            if (in_array($type, $allowedTypes, true) && !empty($href)) {
                                if (!str_starts_with($href, 'http')) {
                                    $parts = parse_url($query);
                                    $base = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '');
                                    if (isset($parts['port'])) {
                                        $base .= ':' . $parts['port'];
                                    }
                                    $href = $base . '/' . ltrim($href, '/');
                                }
                                $results[] = [
                                    'type' => 'feed',
                                    'url' => $href,
                                ];
                            }
                        }
                    }
                }
            }

            if (empty($results)) {
                $results[] = [
                    'type' => 'feed',
                    'url' => $query,
                ];
            }
        }

        return $results;
    }

    /**
     * Dispatches an interaction (like, repost, reply, poll vote) to ActivityPub.
     *
     * @return array{success: string, activity_id: string}
     */
    public function interact(string $targetUrl, string $actionType, string $content = ''): array
    {
        if (!$targetUrl || !$actionType) {
            throw new Exception("Missing target URL or action type");
        }

        $ctx = stream_context_create(['http' => ['header' => "Accept: application/activity+json\r\nUser-Agent: Indieinabox/1.0\r\n", 'timeout' => 5]]);
        $postData = $this->fetchUrl($targetUrl, $ctx);
        if (!$postData) {
            throw new Exception("Could not fetch target post");
        }

        $postObj = json_decode($postData, true);
        if (!is_array($postObj)) {
            throw new Exception("Target post is not valid JSON");
        }

        $actorUrl = $postObj['attributedTo'] ?? ($postObj['actor'] ?? '');
        if (is_array($actorUrl)) {
            $actorUrl = $actorUrl['id'] ?? ($actorUrl[0] ?? '');
        }
        if (!$actorUrl || !is_string($actorUrl)) {
            throw new Exception("Could not find actor for target post");
        }

        $actorData = $this->fetchUrl($actorUrl, $ctx);
        if (!$actorData) {
            throw new Exception("Could not fetch actor profile");
        }

        $actorObj = json_decode($actorData, true);
        if (!is_array($actorObj)) {
            throw new Exception("Actor profile is not valid JSON");
        }

        $inboxUrl = $actorObj['inbox'] ?? '';
        if (!$inboxUrl) {
            throw new Exception("Actor has no inbox");
        }

        $fqdn = rtrim(Database::getSetting('fqdn') ?? 'http://localhost', '/');
        $myActor = $fqdn . '/actor';
        $activityId = $fqdn . '/activity/' . uniqid();

        $payload = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'actor' => $myActor,
        ];

        if ($actionType === 'like') {
            $payload['type'] = 'Like';
            $payload['object'] = $targetUrl;
        } elseif ($actionType === 'repost') {
            $payload['type'] = 'Announce';
            $payload['object'] = $targetUrl;
        } elseif ($actionType === 'reply') {
            $payload['type'] = 'Create';
            $noteId = $fqdn . '/note/' . uniqid();
            $payload['object'] = [
                'id' => $noteId,
                'type' => 'Note',
                'published' => date('Y-m-d\TH:i:s\Z'),
                'attributedTo' => $myActor,
                'inReplyTo' => $targetUrl,
                'content' => $content,
                'to' => ['https://www.w3.org/ns/activitystreams#Public'],
                'cc' => [$actorUrl],
            ];
        } elseif ($actionType === 'poll_vote') {
            $payload['type'] = 'Create';
            $noteId = $fqdn . '/note/' . uniqid();
            $payload['object'] = [
                'id' => $noteId,
                'type' => 'Note',
                'name' => $content,
                'attributedTo' => $myActor,
                'inReplyTo' => $targetUrl,
                'to' => [$actorUrl],
            ];
        } else {
            throw new Exception("Invalid action type");
        }

        $sql = "INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) VALUES (?, ?, 'pending', ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([json_encode($payload, JSON_UNESCAPED_SLASHES), $inboxUrl, time()]);

        return ['success' => 'ok', 'activity_id' => $activityId];
    }

    /**
     * Triggers sync of all feeds.
     */
    public function syncFeeds(): int
    {
        return $this->feedFetcher->fetchAll();
    }

    protected function fetchUrl(string $url, $context = null)
    {
        return @file_get_contents($url, false, $context);
    }
}
