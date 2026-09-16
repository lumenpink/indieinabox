<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Http\Controllers\ActivityPubController;
use Indieinabox\Http\Controllers\AdminController;
use Indieinabox\Http\Controllers\ArchiveController;
use Indieinabox\Http\Controllers\IndieAuthController;
use Indieinabox\Http\Controllers\MicropubController;
use Indieinabox\Http\Controllers\MicrosubController;
use Indieinabox\Http\Controllers\WebmentionController;
use Indieinabox\Http\StaticFileServer;

/**
 * Class WebRouter
 *
 * Handles incoming HTTP requests by mapping the request URI to the appropriate
 * controller (e.g., Micropub, Microsub, Admin panel, ActivityPub, Webmention, Archive).
 * If no specific controller matches, it delegates to StaticFileServer.
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
     * Initializes the WebRouter with the global site configuration and static file server.
     *
     * @param Site $site The site configuration object.
     * @param StaticFileServer|null $fileServer The static file server instance.
     */
    public function __construct(Site $site, ?StaticFileServer $fileServer = null)
    {
        $this->site = $site;
        $this->fileServer = $fileServer ?? new StaticFileServer($site);
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
        $isAuthParam = isset($_GET['auth']);
        $isAuthPath = (preg_match('#^/auth$#i', $requestUriClean) === 1);
        $isTokenParam = isset($_GET['token']);
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

        $this->serveStatic();
    }

    public function getWebmentionController(): WebmentionController
    {
        return new WebmentionController($this->site);
    }

    public function getIndieAuthController(): IndieAuthController
    {
        return new IndieAuthController($this->site);
    }

    public function getMicropubController(): MicropubController
    {
        return new MicropubController($this->site);
    }

    public function getMicrosubController(): MicrosubController
    {
        return new MicrosubController($this->site);
    }

    public function getActivityPubController(): ActivityPubController
    {
        return new ActivityPubController($this->site);
    }

    public function getArchiveController(): ArchiveController
    {
        return new ArchiveController($this->site);
    }

    public function getAdminController(): AdminController
    {
        return new AdminController($this->site);
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
}
