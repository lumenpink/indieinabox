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

        $outboxUrl = $json['outbox'] ?? '';
        if (!$outboxUrl) return;

        $ctx = stream_context_create(['http' => ['header' => "Accept: application/activity+json\r\nUser-Agent: Indieinabox/1.0\r\n"]]);
        $outboxData = @file_get_contents($outboxUrl, false, $ctx);
        if (!$outboxData) return;

        $outbox = json_decode($outboxData, true);
        if (isset($outbox['first'])) {
            $firstUrl = is_string($outbox['first']) ? $outbox['first'] : ($outbox['first']['id'] ?? '');
            if ($firstUrl) {
                $pageData = @file_get_contents($firstUrl, false, $ctx);
                if ($pageData) {
                    $page = json_decode($pageData, true);
                    $items = $page['orderedItems'] ?? $page['items'] ?? [];
                    foreach ($items as $item) {
                        $obj = is_string($item) ? null : ($item['object'] ?? $item);
                        if (!is_array($obj)) continue;
                        
                        $id = $obj['id'] ?? md5(json_encode($obj));
                        $url = $obj['url'] ?? $id;
                        $contentHtml = $obj['content'] ?? $obj['summary'] ?? '';
                        
                        $inReplyTo = $obj['inReplyTo'] ?? $obj['quote'] ?? $obj['_misskey_quote'] ?? '';
                        if ($inReplyTo && is_string($inReplyTo)) {
                            $parentData = @file_get_contents($inReplyTo, false, $ctx);
                            if ($parentData) {
                                $parentObj = json_decode($parentData, true);
                                if ($parentObj && is_array($parentObj)) {
                                    $parentContent = $parentObj['content'] ?? $parentObj['summary'] ?? '';
                                    
                                    if (!empty($parentObj['attachment']) && is_array($parentObj['attachment'])) {
                                        foreach ($parentObj['attachment'] as $att) {
                                            if (isset($att['type']) && $att['type'] === 'Document' && isset($att['url'])) {
                                                if (strpos($att['mediaType'] ?? '', 'image/') === 0) {
                                                    $parentContent .= '<div style="margin-top: 1rem;"><img src="' . htmlspecialchars($att['url']) . '" style="max-width: 100%; border-radius: 8px;"></div>';
                                                }
                                            }
                                        }
                                    }
                                    
                                    $parentAuthorUrl = $parentObj['attributedTo'] ?? $parentObj['actor'] ?? '';
                                    $parentAuthorName = 'Unknown';
                                    if (is_string($parentAuthorUrl) && $parentAuthorUrl) {
                                        $parentAuthorData = @file_get_contents($parentAuthorUrl, false, $ctx);
                                        if ($parentAuthorData) {
                                            $parentAuthorObj = json_decode($parentAuthorData, true);
                                            if ($parentAuthorObj) {
                                                $parentAuthorName = $parentAuthorObj['name'] ?? $parentAuthorObj['preferredUsername'] ?? 'Unknown';
                                            }
                                        }
                                    }
                                    
                                    $quoteBlock = "<blockquote style=\"border-left: 4px solid var(--primary); background: rgba(0, 0, 0, 0.2); padding: 1rem; margin-bottom: 1rem; border-radius: 4px;\">";
                                    $quoteBlock .= "<div style=\"margin-bottom: 0.5rem; font-size: 0.9em; opacity: 0.8;\"><strong>" . htmlspecialchars($parentAuthorName) . "</strong> wrote:</div>";
                                    $quoteBlock .= $parentContent;
                                    $quoteBlock .= "</blockquote>";
                                    
                                    $contentHtml = $quoteBlock . $contentHtml;
                                }
                            }
                        }
                        
                        if (!empty($obj['attachment']) && is_array($obj['attachment'])) {
                            foreach ($obj['attachment'] as $att) {
                                if (isset($att['type']) && $att['type'] === 'Document' && isset($att['url'])) {
                                    if (strpos($att['mediaType'] ?? '', 'image/') === 0) {
                                        $contentHtml .= '<div style="margin-top: 1rem;"><img src="' . htmlspecialchars($att['url']) . '" style="max-width: 100%; border-radius: 8px;"></div>';
                                    }
                                }
                            }
                        }

                        $published = isset($obj['published']) ? strtotime($obj['published']) : time();
                        
                        $this->saveItem((string)$id, $channel, $url, $contentHtml, $published, $authorName, $authorPhoto);
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

                $content = (string)($item->description ?? '');
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
    private function parseAtom(string $channel, string $feedUrl, SimpleXMLElement $xml): void
    {
        $authorName = (string)($xml->title ?? 'Unknown');
        
        if (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $id = (string)($entry->id ?? '');
                
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

                $published = isset($entry->published) ? strtotime((string)$entry->published) : (isset($entry->updated) ? strtotime((string)$entry->updated) : time());
                
                $entryAuthor = isset($entry->author->name) ? (string)$entry->author->name : $authorName;

                $this->saveItem($id, $channel, $url, $content, $published, $entryAuthor, '');
            }
        }
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
}
