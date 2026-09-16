<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Exception;
use Indieinabox\IndieAuth\TokenManager;
use Indieinabox\Services\MicrosubService;
use Indieinabox\Site;
use Indieinabox\Views\Admin\MicrosubReaderView;

/**
 * Controller handling Microsub server endpoints (channels, timeline, actions) and the reader UI.
 */
class MicrosubController extends AbstractController
{
    protected TokenManager $tokenManager;
    protected MicrosubService $service;

    public function __construct(
        Site $site,
        ?TokenManager $tokenManager = null,
        ?MicrosubService $service = null
    ) {
        parent::__construct($site);
        $this->tokenManager = $tokenManager ?? new TokenManager();
        $this->service = $service ?? new class($this) extends MicrosubService {
            private MicrosubController $controller;

            public function __construct(MicrosubController $controller)
            {
                $this->controller = $controller;
                parent::__construct();
            }

            protected function fetchUrl(string $url, $context = null)
            {
                return $this->controller->getRemoteUrl($url, $context);
            }
        };
    }

    public function getService(): MicrosubService
    {
        return $this->service;
    }

    public function getTokenManager(): TokenManager
    {
        return $this->tokenManager;
    }

    /**
     * Handles standard Microsub API endpoint requests.
     */
    public function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        $tokenData = $this->tokenManager->validateBearerToken();

        if (!$tokenData && empty($_SESSION['admin_authenticated'])) {
            $this->json(['error' => 'unauthorized', 'error_description' => 'Missing or invalid token'], 401);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = (string) ($_REQUEST['action'] ?? '');

        if ($method === 'GET') {
            $this->handleGet($action);
        } elseif ($method === 'POST') {
            $this->handlePost($action);
        } else {
            $this->json(['error' => 'invalid_request', 'error_description' => 'Method not allowed'], 405);
        }
    }

    /**
     * Handles Microsub GET actions (channels, timeline, search, follow).
     */
    protected function handleGet(string $action): void
    {
        switch ($action) {
            case 'channels':
                $channels = $this->service->getChannels();
                $this->json(['channels' => $channels]);
                break;

            case 'timeline':
                $channel = (string) ($_GET['channel'] ?? 'inbox');
                $before = (int) ($_GET['before'] ?? 0);
                $after = (int) ($_GET['after'] ?? 0);
                $timeline = $this->service->getTimeline($channel, $before, $after);
                $this->json($timeline);
                break;

            case 'search':
                $query = (string) ($_GET['query'] ?? ($_GET['url'] ?? ''));
                $results = $this->service->search($query);
                $this->json(['results' => $results]);
                break;

            case 'follow':
                $channel = (string) ($_GET['channel'] ?? 'inbox');
                $items = $this->service->getSubscriptions($channel);
                $this->json(['items' => $items]);
                break;

            default:
                $this->json(['error' => 'invalid_request', 'error_description' => 'Unknown action'], 400);
                break;
        }
    }

    /**
     * Handles Microsub POST actions (channels, timeline, interact, follow, unfollow, fetch).
     */
    protected function handlePost(string $action): void
    {
        switch ($action) {
            case 'channels':
                $method = $_POST['method'] ?? '';
                if ($method === 'create') {
                    $name = trim((string) ($_POST['name'] ?? ''));
                    if ($name === '') {
                        $this->json(['error' => 'invalid_request', 'error_description' => 'Missing channel name'], 400);
                        return;
                    }
                    try {
                        $res = $this->service->createChannel($name);
                        $this->json($res);
                    } catch (Exception) {
                        $this->json(['error' => 'server_error', 'error_description' => 'Failed to create channel'], 500);
                    }
                } elseif ($method === 'delete') {
                    $uid = trim((string) ($_POST['uid'] ?? ''));
                    if ($uid === '') {
                        $this->json(['error' => 'invalid_request', 'error_description' => 'Missing channel uid'], 400);
                        return;
                    }
                    if ($uid === 'inbox' || $uid === 'notifications') {
                        $this->json(['error' => 'invalid_request', 'error_description' => 'Cannot delete default channels'], 400);
                        return;
                    }
                    try {
                        $this->service->deleteChannel($uid);
                        $this->json(['success' => 'ok']);
                    } catch (Exception) {
                        $this->json(['error' => 'server_error', 'error_description' => 'Failed to delete channel'], 500);
                    }
                } else {
                    $this->json(['error' => 'invalid_request', 'error_description' => 'Unsupported method for channels'], 400);
                }
                break;

            case 'timeline':
                $method = $_POST['method'] ?? '';
                if ($method === 'mark_read') {
                    $channel = (string) ($_POST['channel'] ?? 'inbox');
                    $entryIds = $_POST['entry'] ?? [];
                    if (!is_array($entryIds)) {
                        $entryIds = [$entryIds];
                    }
                    $this->service->markRead($channel, $entryIds);
                    $this->json(['success' => 'ok']);
                } else {
                    $this->json(['error' => 'invalid_request', 'error_description' => 'Unsupported method for timeline'], 400);
                }
                break;

            case 'interact':
                $targetUrl = (string) ($_POST['target_url'] ?? '');
                $actionType = (string) ($_POST['interaction_type'] ?? '');
                $content = (string) ($_POST['content'] ?? '');

                if (!$targetUrl || !$actionType) {
                    $this->json(['error' => 'invalid_request', 'error_description' => 'Missing target or action'], 400);
                    return;
                }

                try {
                    $res = $this->service->interact($targetUrl, $actionType, $content);
                    $this->json($res);
                } catch (Exception $e) {
                    $msg = $e->getMessage();
                    if ($msg === 'Invalid action type') {
                        $this->json(['error' => 'invalid_action'], 400);
                        return;
                    }
                    $this->json(['error' => 'invalid_target', 'error_description' => $msg], 400);
                }
                break;

            case 'follow':
                $channel = (string) ($_POST['channel'] ?? 'inbox');
                $url = (string) ($_POST['url'] ?? '');
                if (!$url) {
                    $this->json(['error' => 'invalid_request', 'error_description' => 'Missing url'], 400);
                    return;
                }
                try {
                    $res = $this->service->follow($channel, $url);
                    $this->json($res);
                } catch (Exception $e) {
                    $this->json(['error' => 'invalid_request', 'error_description' => $e->getMessage()], 400);
                }
                break;

            case 'unfollow':
                $channel = (string) ($_POST['channel'] ?? 'inbox');
                $url = (string) ($_POST['url'] ?? '');
                if (!$url) {
                    $this->json(['error' => 'invalid_request', 'error_description' => 'Missing url'], 400);
                    return;
                }
                try {
                    $this->service->unfollow($channel, $url);
                    $this->json(['success' => 'ok']);
                } catch (Exception $e) {
                    $this->json(['error' => 'invalid_request', 'error_description' => $e->getMessage()], 400);
                }
                break;

            case 'fetch':
                $this->service->syncFeeds();
                $this->json(['success' => 'ok']);
                break;

            default:
                $this->json(['error' => 'invalid_request', 'error_description' => 'Unknown action'], 400);
                break;
        }
    }

    /**
     * Handles the Microsub web reader interface.
     */
    public function reader(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        if (empty($_SESSION['admin_authenticated'])) {
            $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
            $this->redirect($fqdn . '/admin/config');
            return;
        }

        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $this->html(MicrosubReaderView::render($fqdn));
    }

    /**
     * Proxy helper for remote URL fetching.
     */
    public function getRemoteUrl(string $url, $context = null): string|false
    {
        return $this->fetchUrl($url, $context);
    }

    /**
     * Helper to fetch remote URL contents. Overridable in tests to avoid real network access.
     */
    protected function fetchUrl(string $url, $context = null): string|false
    {
        return @file_get_contents($url, false, $context);
    }
}
