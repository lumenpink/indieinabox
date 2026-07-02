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
     * Method __construct
     * @param \Indieinabox\Site $site
     */
    public function __construct(Site $site)
    {
        $this->authHandler = new IndieAuthHandler($site);
        $this->db = Database::getDb();
    }

    /**
     * Method handleRequest
     * @return void
     */
    public function handleRequest(): void
    {
        $tokenData = $this->authHandler->validateBearerToken();

        if (!$tokenData) {
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
     * Method handleGet
     * @param string $action
     * 
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

            default:
                http_response_code(400);
                echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unknown action']);
                break;
        }
    }

    /**
     * Method handlePost
     * @param string $action
     * 
     * @return void
     */
    private function handlePost(string $action): void
    {
        switch ($action) {
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

            case 'follow':
                $channel = $_POST['channel'] ?? 'inbox';
                $url = $_POST['url'] ?? '';
                if ($url) {
                    $sql = 'INSERT INTO microsub_subscriptions (channel_uid, url) VALUES (:channel, :url)';
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
                    $stmt->bindValue(':url', $url, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    echo json_encode([
                        'type' => 'feed',
                        'url' => $url
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
