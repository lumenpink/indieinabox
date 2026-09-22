<?php

declare(strict_types=1);

namespace Indieinabox\Http;

use Indieinabox\Core\Container;
use Indieinabox\Http\Controllers\ActivityPubController;
use Indieinabox\Http\Controllers\AdminController;
use Indieinabox\Http\Controllers\ArchiveController;
use Indieinabox\Http\Controllers\ClientController;
use Indieinabox\Http\Controllers\DiscoveryController;
use Indieinabox\Http\Controllers\IndieAuthController;
use Indieinabox\Http\Controllers\MicropubController;
use Indieinabox\Http\Controllers\MicrosubController;
use Indieinabox\Http\Controllers\WebmentionController;
use Indieinabox\Http\StaticFileServer;
use Indieinabox\Site\Site;

/**
 * Class WebRouter
 *
 * Dedicated web dispatcher for the built-in PHP development server and single-file executable.
 * Routes clean URLs and queries to transport controllers or serves static files.
 */
class WebRouter
{
    /**
     * @var Site
     */
    protected Site $site;

    /**
     * @var StaticFileServer
     */
    protected StaticFileServer $fileServer;

    /**
     * @var Container
     */
    protected Container $container;

    /**
     * Initializes the WebRouter with the global site configuration, static file server, and container.
     *
     * @param Site $site The site configuration object.
     * @param StaticFileServer|null $fileServer The static file server instance.
     * @param Container|null $container The dependency injection container.
     */
    public function __construct(Site $site, ?StaticFileServer $fileServer = null, ?Container $container = null)
    {
        $this->site = $site;
        $this->fileServer = $fileServer ?? new StaticFileServer($site);
        $this->container = $container ?? Container::getInstance();
        if (!$this->container->has(Site::class)) {
            $this->container->instance(Site::class, $site);
        }
    }

    public function getFileServer(): StaticFileServer
    {
        return $this->fileServer;
    }

    /**
     * Main entry point for routing requests.
     * Parses the current REQUEST_URI, checks against known API/Admin endpoints,
     * and delegates to the respective handler. Falls back to serveStatic().
     *
     * @return void
     */
    public function handleRequest(): void
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $requestUriClean = rtrim($requestUri, '/');

        // Route: Webmentions
        $isWebmentionParam = isset($_GET['webmention']);
        $isWebmentionPath = (preg_match('#^/webmentions?$#i', $requestUriClean) === 1);

        if ($isWebmentionParam || $isWebmentionPath) {
            $this->getWebmentionController()->handle();
            return;
        }

        // Route: IndieAuth & OAuth Discovery
        $isRootPath = ($requestUriClean === '' || $requestUriClean === '/index.php');
        $isAuthParam = isset($_GET['auth']) && $isRootPath;
        $isAuthPath = (preg_match('#^/auth$#i', $requestUriClean) === 1);
        $isTokenParam = isset($_GET['token']) && $isRootPath;
        $isTokenPath = (preg_match('#^/token$#i', $requestUriClean) === 1);
        $isMetadataPath = ($requestUriClean === '/.well-known/oauth-authorization-server');

        if ($isAuthParam || $isAuthPath || $isTokenParam || $isTokenPath || $isMetadataPath) {
            $this->getIndieAuthController()->handle();
            return;
        }

        // Route: Micropub Discovery Redirect
        if ($requestUriClean === '/.well-known/micropub') {
            header('HTTP/1.1 302 Found');
            header('Location: /micropub');
            return;
        }

        // Route: Micropub Client (Admin Publishing)
        if (str_starts_with($requestUriClean, '/micropub/client')) {
            $this->getMicropubController()->client();
            return;
        }

        // Route: Micropub Endpoint
        if (str_starts_with($requestUriClean, '/micropub')) {
            $this->getMicropubController()->handle();
            return;
        }

        // Route: Microsub Reader (Admin)
        if (str_starts_with($requestUriClean, '/microsub/reader')) {
            $this->getMicrosubController()->reader();
            return;
        }

        // Route: Microsub Endpoint
        if (str_starts_with($requestUriClean, '/microsub')) {
            $this->getMicrosubController()->handle();
            return;
        }

        // Route: ActivityPub (if enabled)
        if (!empty($this->site->config['activitypub_enabled'])) {
            $ap = $this->getActivityPubController();
            if ($requestUriClean === '/interact') {
                $ap->interact();
                return;
            }

            if ($requestUriClean === '/authorize_interaction') {
                $ap->authorizeInteraction();
                return;
            }

            if ($requestUriClean === '/.well-known/webfinger') {
                $ap->webfinger();
                return;
            }

            if ($requestUriClean === '/actor') {
                $ap->actor();
                return;
            }

            if ($requestUriClean === '/inbox') {
                $ap->inbox();
                return;
            }

            if ($requestUriClean === '/outbox') {
                $ap->outbox();
                return;
            }
        }

        // Route: Build Webhook
        if ($requestUriClean === '/build') {
            $this->getAdminController()->build();
            return;
        }

        // Route: Cron Background Worker
        if ($requestUriClean === '/cron') {
            $this->getAdminController()->cron();
            return;
        }

        // Route: Archive Viewer and Force Snapshot
        if ($requestUriClean === '/archive') {
            $this->getArchiveController()->handle();
            return;
        }

        if ($requestUriClean === '/archive/force' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->getArchiveController()->force();
            return;
        }

        // Route: Discovery Engine & Global Timeline
        if ($requestUriClean === '/discover' || $requestUriClean === '/global') {
            $this->getDiscoveryController()->handle();
            return;
        }

        // Route: Unified Client (/client or /reader)
        if ($requestUriClean === '/client' || $requestUriClean === '/reader') {
            $this->getClientController()->handle();
            return;
        }

        // Admin Routes
        if (str_starts_with($requestUriClean, '/admin')) {
            $admin = $this->getAdminController();
            if ($requestUriClean === '/admin') {
                $admin->index();
                return;
            }
            if (str_starts_with($requestUriClean, '/admin/config')) {
                $admin->config();
                return;
            }
            if (str_starts_with($requestUriClean, '/admin/micropub')) {
                $admin->micropub();
                return;
            }
            if (str_starts_with($requestUriClean, '/admin/microsub')) {
                $admin->microsub();
                return;
            }
            if (str_starts_with($requestUriClean, '/admin/moderation')) {
                $admin->moderation();
                return;
            }
        }

        // Route: External Media Proxy (HMAC-signed privacy proxy)
        if ($requestUriClean === '/media/proxy') {
            $this->fileServer->serveProxiedMedia();
            return;
        }

        // Route: Web Push Service Worker
        if ($requestUriClean === '/sw.js') {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Service-Worker-Allowed: /');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            $candidates = [
                dirname(__DIR__, 2) . '/resources/sw.js',
                dirname(__DIR__, 2) . '/public_html/sw.js',
            ];
            foreach ($candidates as $candidate) {
                if (file_exists($candidate) && is_file($candidate)) {
                    readfile($candidate);
                    return;
                }
            }
            echo self::getServiceWorkerScript();
            return;
        }

        $this->serveStatic();
    }

    public function getWebmentionController(): WebmentionController
    {
        return $this->container->make(WebmentionController::class, ['site' => $this->site]);
    }

    public function getIndieAuthController(): IndieAuthController
    {
        return $this->container->make(IndieAuthController::class, ['site' => $this->site]);
    }

    public function getMicropubController(): MicropubController
    {
        return $this->container->make(MicropubController::class, ['site' => $this->site]);
    }

    public function getMicrosubController(): MicrosubController
    {
        return $this->container->make(MicrosubController::class, ['site' => $this->site]);
    }

    public function getActivityPubController(): ActivityPubController
    {
        return $this->container->make(ActivityPubController::class, ['site' => $this->site]);
    }

    public function getArchiveController(): ArchiveController
    {
        return $this->container->make(ArchiveController::class, ['site' => $this->site]);
    }

    public function getDiscoveryController(): DiscoveryController
    {
        return $this->container->make(DiscoveryController::class, ['site' => $this->site]);
    }

    public function getClientController(): ClientController
    {
        return $this->container->make(ClientController::class, ['site' => $this->site]);
    }

    public function getAdminController(): AdminController
    {
        return $this->container->make(AdminController::class, ['site' => $this->site]);
    }

    /**
     * Attempts to serve static files from the output directory via StaticFileServer.
     */
    protected function serveStatic(): void
    {
        $this->fileServer->serve();
    }

    /**
     * Resolves the MIME content-type for a file extension via StaticFileServer.
     *
     * @param string $extension
     * @return string
     */
    public function getMimeType(string $extension): string
    {
        return $this->fileServer->getMimeType($extension);
    }

    /**
     * Fallback inline service worker script for web push notifications.
     */
    public static function getServiceWorkerScript(): string
    {
        return <<<'JS'
self.addEventListener('push', (event) => {
    let data = {
        title: 'Indieinabox Alert',
        body: 'New notification received.',
        url: '/microsub/reader?channel=notifications',
        tag: 'default'
    };

    if (event.data) {
        try {
            data = Object.assign(data, event.data.json());
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: '/media/avatar.png',
        badge: '/media/avatar.png',
        tag: data.tag || 'notification',
        data: {
            url: data.url || '/microsub/reader?channel=notifications'
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const urlToOpen = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/microsub/reader?channel=notifications';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (let client of windowClients) {
                if (client.url.includes(urlToOpen) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});
JS;
    }
}
