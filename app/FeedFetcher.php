<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;
use SimpleXMLElement;
use Exception;

/**
 * Class FeedFetcher
 */
class FeedFetcher
{
    /**
     * @var PDO
     */
    private PDO $db;

    /**
     * Method __construct
     */
    public function __construct()
    {
        $this->db = Database::getDb();
    }

    /**
     * Iterates through all channels and subscriptions, fetching new items for each.
     *
     * @return void
     */
    public function fetchAll(): void
    {
        $stmt = $this->db->query('SELECT id, channel_uid, url FROM microsub_subscriptions');
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subs as $sub) {
            try {
                $this->fetchSubscription($sub['channel_uid'], $sub['url']);
            } catch (Exception $e) {
                error_log("FeedFetcher: Failed to fetch " . $sub['url'] . " - " . $e->getMessage());
            }
        }
    }

    /**
     * Fetches and parses a single subscription URL.
     * Automatically detects the feed format (JSON Feed, RSS, Atom, Twtxt).
     *
     * @param string $channel The Microsub channel ID (e.g., 'timeline').
     * @param string $url The subscription URL to fetch.
     * @return void
     */
    private function fetchSubscription(string $channel, string $url): void
    {
        $ctx = stream_context_create(['http' => ['header' => "Accept: application/activity+json, application/json, application/rss+xml, application/atom+xml, text/html\r\nUser-Agent: Indieinabox/1.0\r\n"]]);
        $content = @file_get_contents($url, false, $ctx);
        if ($content === false) {
            throw new Exception("Could not retrieve URL content.");
        }

        $content = trim($content);
        
        // Is it twtxt?
        if (strpos($content, '# nick') === 0 || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}/m', $content)) {
            $this->parseTwtxt($channel, $url, $content);
            return;
        }

        // Is it JSON Feed or ActivityPub?
        if (strpos($content, '{') === 0) {
            $json = json_decode($content, true);
            if (isset($json['@context']) && in_array('https://www.w3.org/ns/activitystreams', (array)$json['@context'])) {
                $this->parseActivityPub($channel, $url, $json);
                return;
            }
            if (isset($json['version']) && strpos($json['version'], 'https://jsonfeed.org/version/') === 0) {
                $this->parseJsonFeed($channel, $url, $json);
                return;
            }
        }

        // Assume XML (RSS/Atom)
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml !== false) {
            if (isset($xml->channel)) {
                $this->parseRss($channel, $url, $xml);
            } elseif (isset($xml->entry)) {
                $this->parseAtom($channel, $url, $xml);
            }
        }
    }

    /**
     * Parses a Twtxt format feed and saves new entries.
     *
     * @param string $channel The Microsub channel ID.
     * @param string $feedUrl The source URL.
     * @param string $content The raw Twtxt feed content.
     * @return void
     */
    private function parseTwtxt(string $channel, string $feedUrl, string $content): void
    {
        $lines = explode("\n", $content);
        $authorName = 'Unknown';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '# nick') === 0) {
                $parts = explode('=', $line);
                if (count($parts) === 2) {
                    $authorName = trim($parts[1]);
                }
            } elseif (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2}))\s+(.*)$/', $line, $matches)) {
                $published = strtotime($matches[1]);
                $text = $matches[2];
                $id = md5($feedUrl . $published . $text);

                $this->saveItem($id, $channel, $feedUrl, $text, $published, $authorName, '');
            }
        }
    }

    /**
     * Parses a JSON Feed and saves new entries.
     *
     * @param string $channel The Microsub channel ID.
     * @param string $feedUrl The source URL.
     * @param array $json The parsed JSON Feed data.
     * @return void
     */
    private function parseJsonFeed(string $channel, string $feedUrl, array $json): void
    {
        $authorName = $json['title'] ?? 'Unknown';
        
        if (isset($json['items']) && is_array($json['items'])) {
            foreach ($json['items'] as $item) {
                $id = $item['id'] ?? md5(json_encode($item));
                $url = $item['url'] ?? $feedUrl;
                $contentHtml = $item['content_html'] ?? $item['content_text'] ?? '';
                $published = isset($item['date_published']) ? strtotime($item['date_published']) : time();
                
                $itemAuthor = $item['author']['name'] ?? $authorName;
                $itemAvatar = $item['author']['avatar'] ?? '';

                $this->saveItem((string)$id, $channel, $url, $contentHtml, $published, $itemAuthor, $itemAvatar);
            }
        }
    }

    /**
     * Parses an ActivityPub Actor profile and fetches their outbox.
     *
     * @param string $channel The Microsub channel ID.
     * @param string $feedUrl The source URL.
     * @param array $json The parsed JSON ActivityPub data.
     * @return void
     */
    private function parseActivityPub(string $channel, string $feedUrl, array $json): void
    {
        $authorName = $json['name'] ?? $json['preferredUsername'] ?? 'Unknown';
        $authorPhoto = $json['icon']['url'] ?? '';
        if ($authorPhoto) {
            $authorPhoto = $this->downloadMedia($authorPhoto, 'image');
        }

        $outboxUrl = $json['outbox'] ?? '';
        if (!$outboxUrl) return;

        $ctx = stream_context_create(['http' => ['header' => "Accept: application/activity+json\r\nUser-Agent: Indieinabox/1.0\r\n"]]);
        $outboxData = $this->fetchApJson($outboxUrl, $ctx);
        if (!$outboxData) return;

        $outbox = json_decode($outboxData, true);
        if (isset($outbox['first'])) {
            $firstUrl = is_string($outbox['first']) ? $outbox['first'] : ($outbox['first']['id'] ?? '');
            if ($firstUrl) {
                $pageData = $this->fetchApJson($firstUrl, $ctx);
                if ($pageData) {
                    $page = json_decode($pageData, true);
                    $items = $page['orderedItems'] ?? $page['items'] ?? [];
                    foreach ($items as $item) {
                        $obj = is_string($item) ? null : ($item['object'] ?? $item);
                        if (!is_array($obj)) continue;
                        
                        $id = $obj['id'] ?? md5(json_encode($obj));
                        if ($this->itemExists((string)$id, $channel)) {
                            continue;
                        }

                        $url = $obj['url'] ?? $id;
                        $contentHtml = $obj['content'] ?? $obj['summary'] ?? '';
                        $contentHtml = $this->processHtmlMedia($contentHtml);
                        
                        $inReplyTo = $obj['inReplyTo'] ?? $obj['quote'] ?? $obj['_misskey_quote'] ?? '';
                        if ($inReplyTo && is_string($inReplyTo)) {
                            $parentData = $this->fetchApJson($inReplyTo, $ctx);
                            if ($parentData) {
                                $parentObj = json_decode($parentData, true);
                                if ($parentObj && is_array($parentObj)) {
                                    $parentContent = $parentObj['content'] ?? $parentObj['summary'] ?? '';
                                    $parentContent = $this->processHtmlMedia($parentContent);
                                    
                                    if (!empty($parentObj['attachment']) && is_array($parentObj['attachment'])) {
                                        foreach ($parentObj['attachment'] as $att) {
                                            if (isset($att['type']) && $att['type'] === 'Document' && isset($att['url'])) {
                                                if (strpos($att['mediaType'] ?? '', 'image/') === 0) {
                                                    $localUrl = $this->downloadMedia($att['url'], 'image');
                                                    $parentContent .= '<div style="margin-top: 1rem;"><img src="' . htmlspecialchars($localUrl) . '" style="max-width: 100%; border-radius: 8px;"></div>';
                                                }
                                            }
                                        }
                                    }
                                    
                                    $parentAuthorUrl = $parentObj['attributedTo'] ?? $parentObj['actor'] ?? '';
                                    $parentAuthorName = 'Unknown';
                                    if (is_string($parentAuthorUrl) && $parentAuthorUrl) {
                                        $parentAuthorData = $this->fetchApJson($parentAuthorUrl, $ctx);
                                        if ($parentAuthorData) {
                                            $parentAuthorObj = json_decode($parentAuthorData, true);
                                            if ($parentAuthorObj && isset($parentAuthorObj['name'])) {
                                                $parentAuthorName = $parentAuthorObj['name'] ?? $parentAuthorObj['preferredUsername'] ?? 'Unknown';
                                            }
                                        }
                                        
                                        // Fallback to Mentions in the original post if fetching the actor failed (e.g. requires HTTP signature)
                                        if ($parentAuthorName === 'Unknown' && !empty($obj['tag']) && is_array($obj['tag'])) {
                                            foreach ($obj['tag'] as $tag) {
                                                if (isset($tag['type']) && $tag['type'] === 'Mention' && isset($tag['href']) && $tag['href'] === $parentAuthorUrl) {
                                                    if (!empty($tag['name'])) {
                                                        $parentAuthorName = ltrim($tag['name'], '@');
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                        
                                        // Final fallback: just use the username from the URL
                                        if ($parentAuthorName === 'Unknown') {
                                            $parts = explode('/', rtrim($parentAuthorUrl, '/'));
                                            $parentAuthorName = end($parts);
                                        }
                                    }
                                    
                                    $quoteBlock = "<blockquote style=\"border-left: 4px solid var(--primary); background: rgba(0, 0, 0, 0.2); padding: 1rem; margin-bottom: 1rem; border-radius: 4px;\">";
                                    $quoteBlock .= "<div style=\"margin-bottom: 0.5rem; font-size: 0.9em; opacity: 0.8;\"><strong>" . htmlspecialchars($parentAuthorName) . "</strong> wrote:</div>";
                                    $quoteBlock .= $parentContent;
                                    
                                    $parentInReplyTo = $parentObj['inReplyTo'] ?? $parentObj['quote'] ?? $parentObj['_misskey_quote'] ?? '';
                                    if ($parentInReplyTo) {
                                        $parentViewUrl = $parentObj['url'] ?? $parentObj['id'] ?? $inReplyTo;
                                        $quoteBlock .= "<div style=\"margin-top: 0.75rem; font-size: 0.85em; opacity: 0.7;\">↳ <a href=\"" . htmlspecialchars(is_string($parentViewUrl) ? $parentViewUrl : $inReplyTo) . "\" target=\"_blank\" style=\"color: var(--accent); text-decoration: none; font-style: italic;\">This post is also a reply. View the full thread...</a></div>";
                                    }
                                    
                                    $quoteBlock .= "</blockquote>";
                                    
                                    $contentHtml = $quoteBlock . $contentHtml;
                                }
                            }
                        }
                        
                        if (!empty($obj['attachment']) && is_array($obj['attachment'])) {
                            foreach ($obj['attachment'] as $att) {
                                if (isset($att['type']) && $att['type'] === 'Document' && isset($att['url'])) {
                                    if (strpos($att['mediaType'] ?? '', 'image/') === 0) {
                                        $localUrl = $this->downloadMedia($att['url'], 'image');
                                        $contentHtml .= '<div style="margin-top: 1rem;"><img src="' . htmlspecialchars($localUrl) . '" style="max-width: 100%; border-radius: 8px;"></div>';
                                    }
                                }
                            }
                        }

                        $published = isset($obj['published']) ? strtotime($obj['published']) : time();
                        
                        $this->saveItem((string)$id, $channel, $url, $contentHtml, $published, $authorName, $authorPhoto);
                    }
                }
            }
        } else {
            // Pixelfed workaround: outbox does not expose posts via AP GET, so we fallback to .atom
            $actorUrl = $json['id'] ?? $json['url'] ?? '';
            if ($actorUrl) {
                $atomUrl = rtrim($actorUrl, '/') . '.atom';
                $fallbackCtx = stream_context_create(['http' => ['header' => "Accept: application/atom+xml\r\nUser-Agent: Indieinabox/1.0\r\n"]]);
                $atomData = @file_get_contents($atomUrl, false, $fallbackCtx);
                if ($atomData) {
                    $xml = @simplexml_load_string($atomData);
                    if ($xml !== false) {
                        $this->parseAtom($channel, $atomUrl, $xml);
                    }
                }
            }
        }
    }

    /**
     * Parses an RSS feed and saves new entries.
     *
     * @param string $channel The Microsub channel ID.
     * @param string $feedUrl The source URL.
     * @param SimpleXMLElement $xml The parsed XML.
     * 
     * @return void
     */
    private function parseRss(string $channel, string $feedUrl, SimpleXMLElement $xml): void
    {
        $authorName = (string)($xml->channel->title ?? 'Unknown');
        
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $url = (string)($item->link ?? '');
                $id = (string)($item->guid ?? $url);
                if (!$id) $id = md5((string)$item->title);

                if ($this->itemExists($id, $channel)) {
                    continue;
                }

                $content = (string)($item->description ?? '');
                $content = $this->processHtmlMedia($content);
                $published = isset($item->pubDate) ? strtotime((string)$item->pubDate) : time();
                
                $this->saveItem($id, $channel, $url, $content, $published, $authorName, '');
            }
        }
    }

    /**
     * Parses an Atom feed.
     *
     * @param string $channel The Microsub channel ID.
     * @param string $feedUrl The source URL.
     * @param SimpleXMLElement $xml The parsed XML.
     * 
     * @return void
     */
    private function parseAtom(string $channel, string $feedUrl, \SimpleXMLElement $xml): void
    {
        $authorName = (string)($xml->title ?? 'Unknown');
        $authorPhoto = '';
        if (isset($xml->icon)) {
            $authorPhoto = $this->downloadMedia((string)$xml->icon, 'image');
        } elseif (isset($xml->logo)) {
            $authorPhoto = $this->downloadMedia((string)$xml->logo, 'image');
        }
        
        if (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $id = (string)($entry->id ?? '');
                
                if ($this->itemExists($id, $channel)) {
                    continue;
                }
                
                $url = '';
                if (isset($entry->link)) {
                    foreach ($entry->link as $link) {
                        if ((string)$link['rel'] === 'alternate' || empty($link['rel'])) {
                            $url = (string)$link['href'];
                            break;
                        }
                    }
                }

                $content = '';
                if (isset($entry->content)) {
                    $content = (string)$entry->content;
                } elseif (isset($entry->summary)) {
                    $content = (string)$entry->summary;
                }
                
                $content = $this->processHtmlMedia($content);

                $published = isset($entry->published) ? strtotime((string)$entry->published) : (isset($entry->updated) ? strtotime((string)$entry->updated) : time());
                
                $entryAuthor = isset($entry->author->name) ? (string)$entry->author->name : $authorName;

                $this->saveItem($id, $channel, $url, $content, $published, $entryAuthor, $authorPhoto);
            }
        }
    }

    private function itemExists(string $id, string $channel): bool
    {
        $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
        $channelDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $channel);
        $filepath = $channelDir . DIRECTORY_SEPARATOR . md5($id) . '.md';
        return file_exists($filepath);
    }

    /**
     * Saves a parsed feed item to the local file system (Microsub item store).
     *
     * @param string $id The unique identifier for the item.
     * @param string $channel The channel ID where the item belongs.
     * @param string $url The source URL of the item.
     * @param string $content The HTML or text content.
     * @param int $published The publication timestamp.
     * @param string $authorName The author's name.
     * @param string $authorPhoto The author's avatar URL.
     * 
     * @return void
     */
    private function saveItem(string $id, string $channel, string $url, string $content, int $published, string $authorName, string $authorPhoto): void
    {
        $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
        
        $channelDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $channel);
        if (!is_dir($channelDir)) {
            @mkdir($channelDir, 0755, true);
        }

        // Generate a safe filename from the ID
        $filename = md5($id) . '.md';
        $filepath = $channelDir . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($filepath)) {
            $frontmatter = [
                'id' => $id,
                'url' => $url,
                'author_name' => $authorName,
                'author_photo' => $authorPhoto,
                'published' => $published,
                'is_read' => 0,
                'type' => 'feed'
            ];
            
            $yaml = new \Indieinabox\Yaml();
            $yamlStr = $yaml->dump($frontmatter);
            $fileContent = "---\n" . $yamlStr . "---\n\n" . $content;
            
            file_put_contents($filepath, $fileContent);
        }
    }

    /**
     * Fetches ActivityPub JSON, automatically attempting HTTP Signatures if available.
     * Uses the provided stream context as a fallback if signing fails or is not possible.
     */
    private function fetchApJson(string $url, $fallbackCtx = null)
    {
        $stmt = $this->db->query("SELECT private_key FROM activitypub_keys WHERE key_id = 'main-key'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && !empty($row['private_key']) && class_exists('\Indieinabox\HttpSignature')) {
            $privateKey = $row['private_key'];
            $fqdn = \Indieinabox\Database::getSetting('fqdn');
            
            if ($fqdn) {
                $fqdn = rtrim($fqdn, '/');
                $keyId = $fqdn . '/actor#main-key';
                
                $sigHeaders = \Indieinabox\HttpSignature::sign(
                    $keyId,
                    $privateKey,
                    'GET',
                    $url,
                    '',
                    ['Accept' => 'application/activity+json']
                );
                
                $headersList = [
                    "Accept: application/activity+json",
                    "User-Agent: Indieinabox/1.0"
                ];
                
                foreach ($sigHeaders as $k => $v) {
                    $headersList[] = "$k: $v";
                }
                
                $ctx = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => implode("\r\n", $headersList) . "\r\n"
                    ]
                ]);
                
                $data = @file_get_contents($url, false, $ctx);
                if ($data) return $data;
            }
        }
        
        return @file_get_contents($url, false, $fallbackCtx);
    }

    /**
     * Replaces remote media URLs in HTML content with local cached URLs.
     */
    private function processHtmlMedia(string $html): string
    {
        // Match <img>, <video>, <audio>, <source> src attributes
        return preg_replace_callback('/<(img|video|audio|source)[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', function($matches) {
            $fullTag = $matches[0];
            $tagType = strtolower($matches[1]);
            $url = $matches[2];
            
            $type = 'image';
            if ($tagType === 'video' || $tagType === 'source') $type = 'video';
            if ($tagType === 'audio') $type = 'audio';
            
            $localUrl = $this->downloadMedia($url, $type);
            
            return str_replace($url, $localUrl, $fullTag);
        }, $html);
    }

    /**
     * Downloads a media file locally.
     */
    private function downloadMedia(string $url, string $type): string
    {
        if (!$url || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $url;
        }

        $fqdn = rtrim(\Indieinabox\Database::getSetting('fqdn') ?? '', '/');
        if (strpos($url, '/media/') === 0 || ($fqdn && strpos($url, $fqdn) === 0)) {
            return $url;
        }

        $enabledStr = \Indieinabox\Database::getSetting("download_media_{$type}");
        $enabled = $enabledStr === null || $enabledStr === '' || $enabledStr === '1' || $enabledStr === 'true'; 
        if (!$enabled) {
            return $url;
        }

        $maxSizeStr = \Indieinabox\Database::getSetting("download_media_max_size_mb");
        $maxSizeMB = $maxSizeStr !== null && $maxSizeStr !== '' ? (float)$maxSizeStr : 10.0;
        $maxSizeBytes = $maxSizeMB * 1024 * 1024;

        $baseDir = dirname(__DIR__) . '/public_media/microsub';
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
        if (!$fp) return $url;

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
        
        if ($maxSizeBytes > 0) {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $download_size, $downloaded, $upload_size, $uploaded) use ($maxSizeBytes) {
                if ($maxSizeBytes > 0 && ($download_size > $maxSizeBytes || $downloaded > $maxSizeBytes)) {
                    return 1;
                }
                return 0;
            });
        }

        curl_setopt($ch, CURLOPT_USERAGENT, 'Indieinabox/1.0');

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
}
