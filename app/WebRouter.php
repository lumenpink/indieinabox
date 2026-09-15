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

/**
 * Class WebRouter
 * 
 * Handles incoming HTTP requests by mapping the request URI to the appropriate
 * handler class (e.g., Micropub, Microsub, Admin panel, ActivityPub, Webmention, Archive).
 * If no specific handler matches, it serves static files or emits 404.
 */
class WebRouter
{
    /**
     * @var Site
     */
    protected Site $site;

    /**
     * Initializes the WebRouter with the global site configuration.
     *
     * @param Site $site The site configuration object.
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
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
        if (strpos($requestUriClean, '/micropub/client') === 0) {
            $this->getMicropubController()->client();
            return;
        }

        // Route: Micropub Endpoint
        if (strpos($requestUriClean, '/micropub') === 0) {
            $this->getMicropubController()->handle();
            return;
        }

        // Route: Microsub Reader (Admin)
        if (strpos($requestUriClean, '/microsub/reader') === 0) {
            $this->getMicrosubController()->reader();
            return;
        }

        // Route: Microsub Endpoint
        if (strpos($requestUriClean, '/microsub') === 0) {
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
        if (strpos($requestUriClean, '/admin') === 0) {
            $admin = $this->getAdminController();
            if ($requestUriClean === '/admin') {
                $admin->index();
                return;
            }
            if (strpos($requestUriClean, '/admin/config') === 0) {
                $admin->config();
                return;
            }
            if (strpos($requestUriClean, '/admin/micropub') === 0) {
                $admin->micropub();
                return;
            }
            if (strpos($requestUriClean, '/admin/microsub') === 0) {
                $admin->microsub();
                return;
            }
            if (strpos($requestUriClean, '/admin/moderation') === 0) {
                $admin->moderation();
                return;
            }
        }

        $this->serveStatic();
    }

    /**
     * Factory method to create a WebmentionHandler instance.
     *
     * @return WebmentionHandler
     */
    protected function createWebmentionHandler(): WebmentionHandler
    {
        return new WebmentionHandler($this->site);
    }

    /**
     * Factory method to create an IndieAuthHandler instance.
     *
     * @return IndieAuthHandler
     */
    protected function createIndieAuthHandler(): IndieAuthHandler
    {
        return new IndieAuthHandler($this->site);
    }

    /**
     * Factory method to create a ConfigHandler instance (Admin panel configuration).
     *
     * @return ConfigHandler
     */
    protected function createConfigHandler(): ConfigHandler
    {
        return new ConfigHandler($this->site);
    }

    /**
     * Factory method to create a MicropubHandler instance (Micropub Server).
     *
     * @return MicropubHandler
     */
    protected function createMicropubHandler(): MicropubHandler
    {
        return new MicropubHandler($this->site);
    }

    /**
     * Factory method to create a MicropubClientHandler instance (Admin panel publishing).
     *
     * @return MicropubClientHandler
     */
    protected function createMicropubClientHandler(): MicropubClientHandler
    {
        return new MicropubClientHandler($this->site);
    }

    /**
     * Factory method to create a MicrosubHandler instance (Microsub Server).
     *
     * @return MicrosubHandler
     */
    protected function createMicrosubHandler(): MicrosubHandler
    {
        return new MicrosubHandler($this->site);
    }

    /**
     * Factory method to create a MicrosubReaderHandler instance (Admin panel reader).
     *
     * @return MicrosubReaderHandler
     */
    protected function createMicrosubReaderHandler(): MicrosubReaderHandler
    {
        return new MicrosubReaderHandler($this->site);
    }

    /**
     * Factory method to create a ModerationHandler instance (Admin panel moderation).
     *
     * @return ModerationHandler
     */
    protected function createModerationHandler(): ModerationHandler
    {
        return new ModerationHandler($this->site);
    }

    /**
     * Factory method to create an ActivityPubHandler instance (Fediverse integration).
     *
     * @return ActivityPubHandler
     */
    protected function createActivityPubHandler(): ActivityPubHandler
    {
        return new ActivityPubHandler($this->site);
    }

    /**
     * Factory method to create an ArchiveHandler instance.
     *
     * @return ArchiveHandler
     */
    protected function createArchiveHandler(): ArchiveHandler
    {
        return new ArchiveHandler($this->site);
    }

    public function getWebmentionController(): WebmentionController
    {
        return new WebmentionController($this->site, $this->createWebmentionHandler());
    }

    public function getIndieAuthController(): IndieAuthController
    {
        return new IndieAuthController($this->site, $this->createIndieAuthHandler());
    }

    public function getMicropubController(): MicropubController
    {
        return new MicropubController($this->site, $this->createMicropubHandler(), $this->createMicropubClientHandler());
    }

    public function getMicrosubController(): MicrosubController
    {
        return new MicrosubController($this->site, $this->createMicrosubHandler(), $this->createMicrosubReaderHandler());
    }

    public function getActivityPubController(): ActivityPubController
    {
        return new ActivityPubController($this->site, $this->createActivityPubHandler());
    }

    public function getArchiveController(): ArchiveController
    {
        return new ArchiveController($this->site, $this->createArchiveHandler());
    }

    public function getAdminController(): AdminController
    {
        return new AdminController(
            $this->site,
            $this->createConfigHandler(),
            $this->createMicropubClientHandler(),
            $this->createMicrosubReaderHandler(),
            $this->createModerationHandler()
        );
    }

    /**
     * Attempts to serve static files from the output directory based on the request URI.
     * Determines MIME types and outputs appropriate headers.
     * Supports content negotiation for ActivityPub requests.
     *
     * @return void
     */
    protected function serveStatic(): void
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $outputDir = $this->site->paths->outputDirHtml;
        $path = str_replace(['..', '//'], ['', '/'], urldecode($requestUri));

        if ($path === '' || $path === '/') {
            $path = '/index.html';
        }

        $base = rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR);
        $filePath = $base . DIRECTORY_SEPARATOR . $outputDir . $path;

        if (strpos($path, '/media/') === 0) {
            $contentMediaPath = rtrim($this->site->paths->getContentPath(), DIRECTORY_SEPARATOR);
            $contentMediaPath .= str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (file_exists($contentMediaPath) && is_file($contentMediaPath)) {
                $filePath = $contentMediaPath;
            }
        }

        if (is_dir($filePath)) {
            $filePath = rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
        }

        $acceptsAP = (
            strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/activity+json') !== false ||
            strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/ld+json') !== false
        );

        if ($acceptsAP) {
            $jsonPath = (string) preg_replace('/\.html$/', '.json', $filePath);
            if (file_exists($jsonPath) && is_file($jsonPath)) {
                header('Content-Type: application/activity+json; charset=utf-8');
                readfile($jsonPath);
                return;
            }
        }

        if (file_exists($filePath) && is_file($filePath)) {
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $contentType = $this->getMimeType($ext);
            header('Content-Type: ' . $contentType);
            readfile($filePath);
            return;
        }

        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 Not Found. File path checked: " . $filePath;
    }

    /**
     * Resolves the MIME content-type for a file extension.
     *
     * @param string $extension
     * @return string
     */
    public function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'html' => 'text/html; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'xml'  => 'application/xml; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'txt'  => 'text/plain; charset=utf-8',
            'gmi'  => 'text/gemini; charset=utf-8',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }
}
