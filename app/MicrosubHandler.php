<?php

declare(strict_types=1);

namespace Indieinabox;

use Exception;
use Indieinabox\IndieAuth\TokenManager;
use Indieinabox\Services\MicrosubService;

/**
 * HTTP handler for Microsub server endpoints (channels, timeline, actions).
 */
class MicrosubHandler
{
    private TokenManager $tokenManager;
    private MicrosubService $service;

    public function __construct(
        Site $site,
        ?TokenManager $tokenManager = null,
        ?MicrosubService $service = null
    ) {
        $this->tokenManager = $tokenManager ?? new TokenManager();
        $this->service = $service ?? new class($this) extends MicrosubService {
            private MicrosubHandler $handler;

            public function __construct(MicrosubHandler $handler)
            {
                $this->handler = $handler;
                parent::__construct();
            }

            protected function fetchUrl(string $url, $context = null)
            {
                return $this->handler->getRemoteUrl($url, $context);
            }
        };
    }

    /**
     * Main entry point for handling Microsub requests.
     */
    public function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        $tokenData = $this->tokenManager->validateBearerToken();

        if (!$tokenData && empty($_SESSION['admin_authenticated'])) {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorized', 'error_description' => 'Missing or invalid token']);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_REQUEST['action'] ?? '';

        header('Content-Type: application/json');

        if ($method === 'GET') {
            $this->handleGet((string)$action);
        } elseif ($method === 'POST') {
            $this->handlePost((string)$action);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'invalid_request', 'error_description' => 'Method not allowed']);
        }
    }

    /**
     * Handles Microsub GET actions (channels, timeline, search, follow).
     */
    private function handleGet(string $action): void
    {
        switch ($action) {
            case 'channels':
                $channels = $this->service->getChannels();
                echo json_encode(['channels' => $channels]);
                break;

            case 'timeline':
                $channel = $_GET['channel'] ?? 'inbox';
                $before = (int)($_GET['before'] ?? 0);
                $after = (int)($_GET['after'] ?? 0);
                $timeline = $this->service->getTimeline((string)$channel, $before, $after);
                echo json_encode($timeline);
                break;

            case 'search':
                $query = $_GET['query'] ?? ($_GET['url'] ?? '');
                $results = $this->service->search((string)$query);
                echo json_encode(['results' => $results]);
                break;

            case 'follow':
                $channel = $_GET['channel'] ?? 'inbox';
                $items = $this->service->getSubscriptions((string)$channel);
                echo json_encode(['items' => $items]);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unknown action']);
                break;
        }
    }

    /**
     * Handles Microsub POST actions (channels, timeline, interact, follow, unfollow, fetch).
     */
    private function handlePost(string $action): void
    {
        switch ($action) {
            case 'channels':
                $method = $_POST['method'] ?? '';
                if ($method === 'create') {
                    $name = trim($_POST['name'] ?? '');
                    if ($name === '') {
                        http_response_code(400);
                        echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing channel name']);
                        return;
                    }
                    try {
                        $res = $this->service->createChannel($name);
                        echo json_encode($res);
                    } catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode(['error' => 'server_error', 'error_description' => 'Failed to create channel']);
                    }
                } elseif ($method === 'delete') {
                    $uid = trim($_POST['uid'] ?? '');
                    if ($uid === '') {
                        http_response_code(400);
                        echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing channel uid']);
                        return;
                    }
                    if ($uid === 'inbox' || $uid === 'notifications') {
                        http_response_code(400);
                        echo json_encode(['error' => 'invalid_request', 'error_description' => 'Cannot delete default channels']);
                        return;
                    }
                    try {
                        $this->service->deleteChannel($uid);
                        echo json_encode(['success' => 'ok']);
                    } catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode(['error' => 'server_error', 'error_description' => 'Failed to delete channel']);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unsupported method for channels']);
                }
                break;

            case 'timeline':
                $method = $_POST['method'] ?? '';
                if ($method === 'mark_read') {
                    $channel = $_POST['channel'] ?? 'inbox';
                    $entryIds = $_POST['entry'] ?? [];
                    if (!is_array($entryIds)) {
                        $entryIds = [$entryIds];
                    }
                    $this->service->markRead((string)$channel, $entryIds);
                    echo json_encode(['success' => 'ok']);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unsupported method for timeline']);
                }
                break;

            case 'interact':
                $targetUrl = $_POST['target_url'] ?? '';
                $actionType = $_POST['interaction_type'] ?? '';
                $content = $_POST['content'] ?? '';

                if (!$targetUrl || !$actionType) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing target or action']);
                    return;
                }

                try {
                    $res = $this->service->interact($targetUrl, $actionType, $content);
                    echo json_encode($res);
                } catch (Exception $e) {
                    http_response_code(400);
                    $msg = $e->getMessage();
                    if ($msg === 'Invalid action type') {
                        echo json_encode(['error' => 'invalid_action']);
                        return;
                    }
                    echo json_encode(['error' => 'invalid_target', 'error_description' => $msg]);
                }
                break;

            case 'follow':
                $channel = $_POST['channel'] ?? 'inbox';
                $url = $_POST['url'] ?? '';
                if (!$url) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing url']);
                    return;
                }
                try {
                    $res = $this->service->follow((string)$channel, (string)$url);
                    echo json_encode($res);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => $e->getMessage()]);
                }
                break;

            case 'unfollow':
                $channel = $_POST['channel'] ?? 'inbox';
                $url = $_POST['url'] ?? '';
                if (!$url) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing url']);
                    return;
                }
                try {
                    $this->service->unfollow((string)$channel, (string)$url);
                    echo json_encode(['success' => 'ok']);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => 'invalid_request', 'error_description' => $e->getMessage()]);
                }
                break;

            case 'fetch':
                $this->service->syncFeeds();
                echo json_encode(['success' => 'ok']);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unknown action']);
                break;
        }
    }

    /**
     * Internal proxy to fetchUrl so anonymous service adapter can invoke it.
     */
    public function getRemoteUrl(string $url, $context = null)
    {
        return $this->fetchUrl($url, $context);
    }

    /**
     * Helper to fetch remote URL contents. Overridable in tests to avoid real network access.
     *
     * @param string $url
     * @param resource|null $context
     * @return string|false
     */
    protected function fetchUrl(string $url, $context = null)
    {
        return @file_get_contents($url, false, $context);
    }
}
