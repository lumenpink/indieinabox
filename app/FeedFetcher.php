<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;
use SimpleXMLElement;
use Exception;

class FeedFetcher
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getDb();
    }

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

    private function fetchSubscription(string $channel, string $url): void
    {
        $content = @file_get_contents($url);
        if ($content === false) {
            throw new Exception("Could not retrieve URL content.");
        }

        $content = trim($content);
        
        // Is it twtxt?
        if (strpos($content, '# nick') === 0 || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}/m', $content)) {
            $this->parseTwtxt($channel, $url, $content);
            return;
        }

        // Is it JSON Feed?
        if (strpos($content, '{') === 0) {
            $json = json_decode($content, true);
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
