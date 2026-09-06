<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;

/**
 * Class MicrosubHandler
 */
class MicrosubHandler
{
    /**
     * @var \Indieinabox\IndieAuthHandler
     */
    private IndieAuthHandler $authHandler;
    /**
     * @var PDO
     */
    private PDO $db;

    /**
     * Initializes the MicrosubHandler.
     *
     * @param \Indieinabox\Site $site Global site configuration and environment.
     */
    public function __construct(Site $site)
    {
        $this->authHandler = new IndieAuthHandler($site);
        $this->db = Database::getDb();
    }

    /**
     * Main entry point for handling Microsub requests.
     * Enforces authentication and routes to handleGet or handlePost.
     *
     * @return void
     */
    public function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        $tokenData = $this->authHandler->validateBearerToken();

        if (!$tokenData && empty($_SESSION['admin_authenticated'])) {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorized', 'error_description' => 'Missing or invalid token']);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_REQUEST['action'] ?? '';

        header('Content-Type: application/json');

        if ($method === 'GET') {
            $this->handleGet($action);
        } elseif ($method === 'POST') {
            $this->handlePost($action);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'invalid_request', 'error_description' => 'Method not allowed']);
        }
    }

    /**
     * Handles Microsub GET actions (channels, timeline, search).
     * Retrieves lists of subscribed feeds or items in a feed.
     *
     * @param string $action The requested action ('channels', 'timeline', 'search', etc).
     * @return void
     */
    private function handleGet(string $action): void
    {
        switch ($action) {
            case 'channels':
                $stmt = $this->db->query('SELECT uid, name FROM microsub_channels');
                $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['channels' => $channels]);
                break;

            case 'timeline':
                $channel = $_GET['channel'] ?? 'inbox';
                $before = (int)($_GET['before'] ?? 0);
                $after = (int)($_GET['after'] ?? 0);
                
                $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
                $channelDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $channel);
                
                $items = [];
                $firstPub = null;
                $lastPub = null;
                
                if (is_dir($channelDir)) {
                    $files = glob($channelDir . DIRECTORY_SEPARATOR . '*.md');
                    if ($files) {
                        $parsedItems = [];
                        foreach ($files as $file) {
                            $content = file_get_contents($file);
                            if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                                $yamlParser = new \Indieinabox\Yaml();
                                $fm = $yamlParser->loadString($matches[1]);
                                $pubInt = (int)($fm['published'] ?? filemtime($file));
                                
                                if ($before && $pubInt <= $before) continue;
                                if ($after && $pubInt >= $after) continue;
                                
                                $parsedItems[] = [
                                    'pubInt' => $pubInt,
                                    'fm' => $fm,
                                    'contentHtml' => trim($matches[2])
                                ];
                            }
                        }
                        
                        usort($parsedItems, function ($a, $b) {
                            return $b['pubInt'] <=> $a['pubInt'];
                        });
                        
                        $parsedItems = array_slice($parsedItems, 0, 20);
                        
                        foreach ($parsedItems as $p) {
                            $pubInt = $p['pubInt'];
                            if ($firstPub === null) {
                                $firstPub = $pubInt;
                            }
                            $lastPub = $pubInt;
                            
                            $fm = $p['fm'];
                            $item = [
                                'type' => 'entry',
                                'url' => $fm['url'] ?? '',
                                'content' => ['html' => $p['contentHtml']],
                                'published' => date('c', $pubInt),
                                '_id' => $fm['id'] ?? basename($file, '.md'),
                                '_is_read' => (bool)($fm['is_read'] ?? false)
                            ];
                            if (!empty($fm['author_name'])) {
                                $item['author'] = [
                                    'type' => 'card',
                                    'name' => $fm['author_name'],
                                    'photo' => $fm['author_photo'] ?? ''
                                ];
                            }
                            $items[] = $item;
                        }
                    }
                }
                
                $response = ['items' => $items];
                if (count($items) > 0) {
                    $response['paging'] = [
                        'before' => $firstPub,
                        'after' => $lastPub
                    ];
                }
                echo json_encode($response);
                break;

            case 'search':
                $query = $_GET['query'] ?? $_GET['url'] ?? '';
                $results = [];

                if (filter_var($query, FILTER_VALIDATE_URL)) {
                    $context = stream_context_create(['http' => ['timeout' => 5]]);
                    $html = @file_get_contents($query, false, $context);
                    if ($html) {
                        $dom = new \DOMDocument();
                        @$dom->loadHTML($html);
                        $xpath = new \DOMXPath($dom);
                        $links = $xpath->query('//link[@rel="alternate"]');
                        foreach ($links as $link) {
                            if ($link instanceof \DOMElement) {
                                $type = $link->getAttribute('type');
                                $href = $link->getAttribute('href');
                                $allowedTypes = [
                                    'application/rss+xml',
                                    'application/atom+xml',
                                    'application/feed+json',
                                    'text/plain'
                                ];
                                if (in_array($type, $allowedTypes) && !empty($href)) {
                                    if (strpos($href, 'http') !== 0) {
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
                    if (empty($results)) {
                        $results[] = [
                            'type' => 'feed',
                            'url' => $query
                        ];
                    }
                }
                echo json_encode(['results' => $results]);
                break;

            case 'follow':
                $channel = $_GET['channel'] ?? 'inbox';
                $stmt = $this->db->prepare('SELECT url, type, name, photo FROM microsub_subscriptions WHERE channel_uid = :channel');
                $stmt->bindValue(':channel', $channel, \PDO::PARAM_STR);
                $stmt->execute();
                $items = [];
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $items[] = [
                        'type' => 'feed',
                        'url' => $row['url'],
                        'feed_type' => $row['type'] ?? 'rss',
                        'name' => $row['name'] ?? $row['url'],
                        'photo' => $row['photo'] ?? ''
                    ];
                }
                echo json_encode(['items' => $items]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unknown action']);
                break;
        }
    }

    /**
     * Handles Microsub POST actions (subscribe, unsubscribe, mute, block, mark read).
     * Modifies subscriptions or state in the underlying JSON data files.
     *
     * @param string $action The requested action.
     * @return void
     */
    private function handlePost(string $action): void
    {
        switch ($action) {
            case 'channels':
                $method = $_POST['method'] ?? '';
                if ($method === 'create') {
                    $name = trim($_POST['name'] ?? '');
                    if ($name) {
                        $uid = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
                        if (!$uid) $uid = 'channel_' . time();
                        $sql = 'INSERT INTO microsub_channels (uid, name) VALUES (:uid, :name)';
                        $stmt = $this->db->prepare($sql);
                        $stmt->bindValue(':uid', $uid, PDO::PARAM_STR);
                        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
                        try {
                            $stmt->execute();
                            echo json_encode(['uid' => $uid, 'name' => $name]);
                        } catch (\PDOException $e) {
                            http_response_code(500);
                            echo json_encode(['error' => 'server_error', 'error_description' => 'Failed to create channel']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing channel name']);
                    }
                } elseif ($method === 'delete') {
                    $uid = trim($_POST['uid'] ?? '');
                    if ($uid) {
                        if ($uid === 'inbox' || $uid === 'notifications') {
                            http_response_code(400);
                            echo json_encode(['error' => 'invalid_request', 'error_description' => 'Cannot delete default channels']);
                            break;
                        }
                        try {
                            $stmt = $this->db->prepare('DELETE FROM microsub_channels WHERE uid = :uid');
                            $stmt->bindValue(':uid', $uid, PDO::PARAM_STR);
                            $stmt->execute();
                            $stmtSubs = $this->db->prepare('DELETE FROM microsub_subscriptions WHERE channel_uid = :uid');
                            $stmtSubs->bindValue(':uid', $uid, PDO::PARAM_STR);
                            $stmtSubs->execute();
                            echo json_encode(['success' => 'ok']);
                        } catch (\PDOException $e) {
                            http_response_code(500);
                            echo json_encode(['error' => 'server_error', 'error_description' => 'Failed to delete channel']);
                        }
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing channel uid']);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unsupported method for channels']);
                }
                break;

            case 'timeline':
                $method = $_POST['method'] ?? '';
                if ($method === 'mark_read') {
                    $channel = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['channel'] ?? 'inbox');
                    $entryIds = $_POST['entry'] ?? [];
                    if (!is_array($entryIds)) {
                        $entryIds = [$entryIds];
                    }

                    $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
                    $channelDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . $channel;

                    foreach ($entryIds as $id) {
                        $possibleFiles = [
                            $channelDir . DIRECTORY_SEPARATOR . $id . '.md',
                            $channelDir . DIRECTORY_SEPARATOR . md5($id) . '.md'
                        ];
                        
                        foreach ($possibleFiles as $file) {
                            if (file_exists($file)) {
                                $content = file_get_contents($file);
                                if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                                    $yamlParser = new \Indieinabox\Yaml();
                                    $fm = $yamlParser->loadString($matches[1]);
                                    if (($fm['id'] ?? '') === $id || md5($fm['id'] ?? '') === md5($id)) {
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
                    echo json_encode(['success' => 'ok']);
                } else {
                    http_response_code(400);
                    echo json_encode([
                        'error' => 'invalid_request',
                        'error_description' => 'Unsupported method for timeline'
                    ]);
                }
                break;

            case 'interact':
                $targetUrl = $_POST['target_url'] ?? '';
                $actionType = $_POST['interaction_type'] ?? ''; // 'like', 'repost', 'reply'
                $content = $_POST['content'] ?? '';

                if (!$targetUrl || !$actionType) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing target or action']);
                    break;
                }

                $ctx = stream_context_create(['http' => ['header' => "Accept: application/activity+json\r\nUser-Agent: Indieinabox/1.0\r\n"]]);
                $postData = @file_get_contents($targetUrl, false, $ctx);
                if (!$postData) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_target', 'error_description' => 'Could not fetch target post']);
                    break;
                }
                $postObj = json_decode($postData, true);
                $actorUrl = $postObj['attributedTo'] ?? $postObj['actor'] ?? '';
                if (is_array($actorUrl)) {
                    $actorUrl = $actorUrl['id'] ?? $actorUrl[0] ?? '';
                }
                if (!$actorUrl || !is_string($actorUrl)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_target', 'error_description' => 'Could not find actor for target post']);
                    break;
                }

                $actorData = @file_get_contents($actorUrl, false, $ctx);
                if (!$actorData) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_target', 'error_description' => 'Could not fetch actor profile']);
                    break;
                }
                $actorObj = json_decode($actorData, true);
                $inboxUrl = $actorObj['inbox'] ?? '';
                if (!$inboxUrl) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_target', 'error_description' => 'Actor has no inbox']);
                    break;
                }

                $fqdn = rtrim(\Indieinabox\Database::getSetting('fqdn') ?? 'http://localhost', '/');
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
                        'cc' => [$actorUrl]
                    ];
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_action']);
                    break;
                }

                $sql = "INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) VALUES (?, ?, 'pending', ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([json_encode($payload, JSON_UNESCAPED_SLASHES), $inboxUrl, time()]);

                echo json_encode(['success' => 'ok', 'activity_id' => $activityId]);
                break;

            case 'follow':
                $channel = $_POST['channel'] ?? 'inbox';
                $url = $_POST['url'] ?? '';
                if ($url) {
                    $type = 'rss';
                    $name = '';
                    $photo = '';
                    $finalUrl = $url;
                    
                    if (preg_match('/^@?([^@]+)@([^@]+)$/', $url, $matches)) {
                        $domain = $matches[2];
                        $user = $matches[1];
                        $wfUrl = "https://{$domain}/.well-known/webfinger?resource=acct:{$user}@{$domain}";
                        $wfCtx = stream_context_create(['http' => ['header' => 'Accept: application/jrd+json']]);
                        $wfData = @file_get_contents($wfUrl, false, $wfCtx);
                        if ($wfData) {
                            $wfJson = json_decode($wfData, true);
                            if (isset($wfJson['links'])) {
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

                    $ctx = stream_context_create(['http' => ['header' => "Accept: application/activity+json, application/json, application/rss+xml, application/atom+xml, text/html\r\nUser-Agent: Indieinabox/1.0\r\n"]]);
                    $content = @file_get_contents($finalUrl, false, $ctx);
                    
                    if ($content) {
                        $content = trim($content);
                        if (strpos($content, '{') === 0) {
                            $json = json_decode($content, true);
                            if (isset($json['@context']) && (in_array('https://www.w3.org/ns/activitystreams', (array)$json['@context']))) {
                                $type = 'ap';
                                $name = $json['name'] ?? $json['preferredUsername'] ?? '';
                                $photo = $json['icon']['url'] ?? '';
                            } elseif (isset($json['version']) && strpos($json['version'], 'https://jsonfeed.org/version/') === 0) {
                                $type = 'json';
                                $name = $json['title'] ?? '';
                                $photo = $json['icon'] ?? $json['favicon'] ?? '';
                            }
                        } elseif (strpos($content, '# nick') === 0 || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}/m', $content)) {
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
                                    $photo = (string)($xml->icon ?? $xml->logo ?? '');
                                }
                            }
                        }
                    }

                    if (!$name) $name = $finalUrl;

                    $sql = 'INSERT INTO microsub_subscriptions (channel_uid, url, type, name, photo) VALUES (:channel, :url, :type, :name, :photo)';
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
                    $stmt->bindValue(':url', $finalUrl, PDO::PARAM_STR);
                    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
                    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
                    $stmt->bindValue(':photo', $photo, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    echo json_encode([
                        'type' => 'feed',
                        'url' => $finalUrl,
                        'feed_type' => $type,
                        'name' => $name
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing url']);
                }
                break;

            case 'unfollow':
                $channel = $_POST['channel'] ?? 'inbox';
                $url = $_POST['url'] ?? '';
                if ($url) {
                    $sql = 'DELETE FROM microsub_subscriptions WHERE channel_uid = :channel AND url = :url';
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
                    $stmt->bindValue(':url', $url, PDO::PARAM_STR);
                    $stmt->execute();
                    echo json_encode(['success' => 'ok']);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing url']);
                }
                break;

            case 'fetch':
                $fetcher = new FeedFetcher();
                $fetcher->fetchAll();
                echo json_encode(['success' => 'ok']);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unknown action']);
                break;
        }
    }
}
