<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;

class MicrosubHandler
{
    private Site $site;
    private IndieAuthHandler $authHandler;
    private PDO $db;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->authHandler = new IndieAuthHandler($site);
        $this->db = Database::getDb();
    }

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
                $stmt = $this->db->prepare('
                    SELECT id, url, content, published, author_name, author_photo, is_read 
                    FROM microsub_items 
                    WHERE channel_uid = :channel 
                    ORDER BY published DESC 
                    LIMIT 20
                ');
                $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
                $stmt->execute();
                
                $items = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $item = [
                        'type' => 'entry',
                        'url' => $row['url'],
                        'content' => ['html' => $row['content']],
                        'published' => date('c', (int)$row['published']),
                        '_id' => $row['id'],
                        '_is_read' => (bool)$row['is_read']
                    ];
                    if (!empty($row['author_name'])) {
                        $item['author'] = [
                            'type' => 'card',
                            'name' => $row['author_name'],
                            'photo' => $row['author_photo'] ?? ''
                        ];
                    }
                    $items[] = $item;
                }
                echo json_encode(['items' => $items]);
                break;

            case 'search':
                // Stub for feed discovery
                $url = $_GET['url'] ?? '';
                echo json_encode([
                    'type' => 'feed',
                    'url' => $url
                ]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unknown action']);
                break;
        }
    }

    private function handlePost(string $action): void
    {
        switch ($action) {
            case 'timeline':
                $method = $_POST['method'] ?? '';
                if ($method === 'mark_read') {
                    $channel = $_POST['channel'] ?? 'inbox';
                    $entryIds = $_POST['entry'] ?? [];
                    if (!is_array($entryIds)) {
                        $entryIds = [$entryIds];
                    }

                    foreach ($entryIds as $id) {
                        $stmt = $this->db->prepare('UPDATE microsub_items SET is_read = 1 WHERE channel_uid = :channel AND id = :id');
                        $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
                        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
                        $stmt->execute();
                    }
                    echo json_encode(['success' => 'ok']);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unsupported method for timeline']);
                }
                break;

            case 'follow':
                $channel = $_POST['channel'] ?? 'inbox';
                $url = $_POST['url'] ?? '';
                if ($url) {
                    $stmt = $this->db->prepare('INSERT INTO microsub_subscriptions (channel_uid, url) VALUES (:channel, :url)');
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
                    $stmt = $this->db->prepare('DELETE FROM microsub_subscriptions WHERE channel_uid = :channel AND url = :url');
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
