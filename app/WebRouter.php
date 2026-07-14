<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class WebRouter
 * 
 * Handles incoming HTTP requests by mapping the request URI to the appropriate
 * handler class (e.g., Micropub, Microsub, Admin panel, ActivityPub, Webmention).
 * If no specific handler matches, it attempts to serve static HTML files.
 */
class WebRouter
{
    /**
     * @var \Indieinabox\Site
     */
    protected Site $site;

    /**
     * Initializes the WebRouter with the global site configuration.
     *
     * @param \Indieinabox\Site $site The site configuration object.
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
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestUriClean = rtrim($requestUri, '/');

        // Route matching
        $isWebmentionParam = isset($_GET['webmention']);
        $isWebmentionPath = (preg_match('#/webmentions?$#i', $requestUriClean) === 1);

        if ($isWebmentionParam || $isWebmentionPath) {
            $handler = $this->createWebmentionHandler();
            $handler->handle();
            return;
        }

        $isAuthParam = isset($_GET['auth']);
        $isAuthPath = (preg_match('#/auth$#i', $requestUriClean) === 1);
        $isTokenParam = isset($_GET['token']);
        $isTokenPath = (preg_match('#/token$#i', $requestUriClean) === 1);
        $isMetadataPath = ($requestUriClean === '/.well-known/oauth-authorization-server');

        if ($isAuthParam || $isAuthPath || $isTokenParam || $isTokenPath || $isMetadataPath) {
            $handler = $this->createIndieAuthHandler();
            $handler->handle();
            return;
        }

        // Route: Micropub
        if ($requestUriClean === '/.well-known/micropub') {
            header('HTTP/1.1 302 Found');
            header('Location: /micropub');
            return;
        }

        if (strpos($requestUriClean, '/micropub/client') === 0) {
            $handler = $this->createMicropubClientHandler();
            $handler->handle();
            return;
        }

        if (strpos($requestUriClean, '/micropub') === 0) {
            $handler = $this->createMicropubHandler();
            $handler->handle();
            return;
        }

        if (strpos($requestUriClean, '/microsub/reader') === 0) {
            $handler = $this->createMicrosubReaderHandler();
            $handler->handleRequest();
            return;
        }

        if (strpos($requestUriClean, '/microsub') === 0) {
            $handler = $this->createMicrosubHandler();
            $handler->handleRequest();
            return;
        }

        if ($requestUriClean === '/.well-known/webfinger') {
            $handler = $this->createActivityPubHandler();
            $handler->handleWebFinger();
            return;
        }

        if ($requestUriClean === '/actor') {
            $handler = $this->createActivityPubHandler();
            $handler->handleActor();
            return;
        }

        if ($requestUriClean === '/inbox') {
            $handler = $this->createActivityPubHandler();
            $handler->handleInbox();
            return;
        }

        if ($requestUriClean === '/outbox') {
            $handler = $this->createActivityPubHandler();
            $handler->handleOutbox();
            return;
        }

        // Admin Routes
        $isAdminPath = (strpos($requestUriClean, '/admin') === 0);
        if ($isAdminPath) {
            if ($requestUriClean === '/admin') {
                header('Location: /admin/config');
                exit;
            }
            if (strpos($requestUriClean, '/admin/config') === 0) {
                $handler = $this->createConfigHandler();
            } elseif (strpos($requestUriClean, '/admin/micropub') === 0) {
                $handler = $this->createMicropubClientHandler();
            } elseif (strpos($requestUriClean, '/admin/microsub') === 0) {
                $handler = $this->createMicrosubReaderHandler();
            } elseif (strpos($requestUriClean, '/admin/moderation') === 0) {
                $handler = $this->createModerationHandler();
            }
        }

        // Backward compatibility for old config route
        $isConfigParam = isset($_GET['config']);
        $isConfigPath = (preg_match('#/config$#i', $requestUriClean) === 1);
        if ($isConfigParam || $isConfigPath) {
            header('Location: /admin/config');
            exit;
        }
        if (isset($handler)) {
            $handler->handle();
            return;
        }

        $this->serveStatic();
    }

    /**
     * Factory method to create a WebmentionHandler instance.
     *
     * @return \Indieinabox\WebmentionHandler
     */
    protected function createWebmentionHandler(): WebmentionHandler
    {
        return new WebmentionHandler($this->site);
    }

    /**
     * Factory method to create an IndieAuthHandler instance.
     *
     * @return \Indieinabox\IndieAuthHandler
     */
    protected function createIndieAuthHandler(): IndieAuthHandler
    {
        return new IndieAuthHandler($this->site);
    }

    /**
     * Factory method to create a ConfigHandler instance (Admin panel configuration).
     *
     * @return \Indieinabox\ConfigHandler
     */
    protected function createConfigHandler(): ConfigHandler
    {
        return new ConfigHandler($this->site);
    }

    /**
     * Factory method to create a MicropubHandler instance (Micropub Server).
     *
     * @return \Indieinabox\MicropubHandler
     */
    protected function createMicropubHandler(): MicropubHandler
    {
        return new MicropubHandler($this->site);
    }

    /**
     * Factory method to create a MicropubClientHandler instance (Admin panel publishing).
     *
     * @return \Indieinabox\MicropubClientHandler
     */
    protected function createMicropubClientHandler(): MicropubClientHandler
    {
        return new MicropubClientHandler($this->site);
    }

    /**
     * Factory method to create a MicrosubHandler instance (Microsub Server).
     *
     * @return \Indieinabox\MicrosubHandler
     */
    protected function createMicrosubHandler(): MicrosubHandler
    {
        return new MicrosubHandler($this->site);
    }

    /**
     * Factory method to create a MicrosubReaderHandler instance (Admin panel reader).
     *
     * @return \Indieinabox\MicrosubReaderHandler
     */
    protected function createMicrosubReaderHandler(): MicrosubReaderHandler
    {
        return new MicrosubReaderHandler($this->site);
    }

    /**
     * Factory method to create a ModerationHandler instance (Admin panel moderation).
     *
     * @return \Indieinabox\ModerationHandler
     */
    protected function createModerationHandler(): ModerationHandler
    {
        return new ModerationHandler($this->site);
    }

    /**
     * Factory method to create an ActivityPubHandler instance (Fediverse integration).
     *
     * @return \Indieinabox\ActivityPubHandler
     */
    protected function createActivityPubHandler(): ActivityPubHandler
    {
        return new ActivityPubHandler($this->site);
    }

    /**
     * Attempts to serve static files from the output directory based on the request URI.
     * Determines MIME types and outputs appropriate caching headers. If the file is 
     * not found, delegates to archive checking.
     *
     * @return void
     */
    private function serveStatic(): void
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestUriClean = rtrim($requestUri, '/');

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
            $jsonPath = preg_replace('/\.html$/', '.json', $filePath);
            if (file_exists($jsonPath) && is_file($jsonPath)) {
                header('Content-Type: application/activity+json; charset=utf-8');
                readfile($jsonPath);
                return;
            }
        }

        if (file_exists($filePath) && is_file($filePath)) {
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $mimeTypes = [
                'html' => 'text/html; charset=utf-8',
                'css' => 'text/css; charset=utf-8',
                'js' => 'application/javascript; charset=utf-8',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'xml' => 'application/xml; charset=utf-8',
                'json' => 'application/json; charset=utf-8',
            ];
            $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $contentType);
            readfile($filePath);
            return;
        }

        if ($requestUriClean === '/archive') {
            $this->handleArchive();
            return;
        }

        if ($requestUriClean === '/archive/force' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleArchiveForce();
            return;
        }

        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 Not Found. File path checked: " . $filePath;
    }

    /**
     * Method handleArchive
     * @return void
     */
    private function handleArchive(): void
    {
        $url = $_GET['url'] ?? '';
        $ts = (int)($_GET['ts'] ?? time());
        
        if (!$url) {
            header('HTTP/1.1 400 Bad Request');
            echo "URL is required";
            return;
        }

        $db = \Indieinabox\Database::getDb();
        
        // Follow alias if exists
        $stmt = $db->prepare("SELECT target_url FROM archive_aliases WHERE alias_url = ?");
        $stmt->execute([$url]);
        if ($row = $stmt->fetch()) {
            $url = $row['target_url'];
        }

        $normUrl = rtrim(strtolower($url), '/');

        // Find closest snapshot by timestamp difference
        $stmt = $db->prepare("SELECT * FROM archived_links WHERE url = ? ORDER BY ABS(timestamp - ?) ASC LIMIT 1");
        $stmt->execute([$normUrl, $ts]);
        $snapshot = $stmt->fetch(\PDO::FETCH_ASSOC);

        $html = '<!DOCTYPE html><html><head><title>Archive View</title>';
        $html .= '<style>
            body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; font-family: system-ui, sans-serif; }
            .archive-bar { background: #1a1a1a; color: #f0f0f0; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; font-size: 14px; }
            .archive-bar .meta { display: flex; align-items: center; gap: 15px; }
            .archive-bar .actions { display: flex; align-items: center; gap: 15px; }
            .archive-bar a { color: #66b3ff; text-decoration: none; font-weight: 500; }
            .archive-bar a:hover { text-decoration: underline; color: #99ccff; }
            .archive-bar form { margin: 0; padding: 0; }
            .archive-bar button { background: #333; color: white; border: 1px solid #555; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; }
            .archive-bar button:hover { background: #444; }
            .archive-frame { width: 100%; height: calc(100% - 46px); border: none; background: #fff; }
        </style>';
        $html .= '</head><body>';
        
        $html .= '<div class="archive-bar">';
        if ($snapshot) {
            $tsSnapshot = (int)$snapshot['timestamp'];
            $date = date('Y-m-d H:i', $tsSnapshot);
            $diffStr = \Indieinabox\Helper::timeAgo($tsSnapshot);
            
            $html .= "<div class=\"meta\">";
            $html .= "<strong>Local Snapshot</strong> <span>{$date} ({$diffStr})</span>";
            $html .= "</div>";

            $html .= '<div class="actions">';
            if ($snapshot['local_pdf_path']) {
                $html .= '<a href="' . htmlspecialchars($snapshot['local_pdf_path']) . '" target="_blank">View PDF</a>';
            }
            if ($snapshot['archive_org_url']) {
                $html .= '<a href="' . htmlspecialchars($snapshot['archive_org_url']) . '" target="_blank">Archive.org</a>';
            }
            $html .= '<a href="' . htmlspecialchars($url) . '" target="_blank" style="color: #ff9999;">Original Site</a>';
            $html .= '<form method="POST" action="/archive/force">';
            $html .= '<input type="hidden" name="url" value="' . htmlspecialchars($url) . '">';
            $html .= '<button type="submit" title="Request a fresh snapshot">Force Update</button>';
            $html .= '</form>';
            $html .= '</div>';
        } else {
            $html .= "<div class=\"meta\">Snapshot processing or not available locally.</div>";
            $html .= '<div class="actions">';
            $html .= '<a href="' . htmlspecialchars($url) . '" target="_blank" style="color: #ff9999;">Original Site</a>';
            $html .= '<form method="POST" action="/archive/force">';
            $html .= '<input type="hidden" name="url" value="' . htmlspecialchars($url) . '">';
            $html .= '<button type="submit" title="Request a fresh snapshot">Force Update</button>';
            $html .= '</form>';
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($snapshot && $snapshot['local_pdf_path']) {
            $pdfUrl = htmlspecialchars($snapshot['local_pdf_path']);
            $html .= "<iframe class=\"archive-frame\" src=\"{$pdfUrl}\"></iframe>";
        } elseif ($snapshot && $snapshot['archive_org_url']) {
            $archiveUrl = htmlspecialchars($snapshot['archive_org_url']);
            $html .= "<iframe class=\"archive-frame\" src=\"{$archiveUrl}\"></iframe>";
        } else {
            $html .= "<div style='padding: 20px;'>No local snapshot available yet. The background worker may still be processing it.</div>";
        }

        $html .= '</body></html>';
        echo $html;
    }

    /**
     * Handles routing for Internet Archive / Wayback Machine fallback requests.
     * Searches for archived versions of requested files.
     *
     * @return void
     */
    private function handleArchiveForce(): void
    {
        $url = $_POST['url'] ?? '';
        if (!$url) {
            header('HTTP/1.1 400 Bad Request');
            echo "URL is required";
            return;
        }

        $db = \Indieinabox\Database::getDb();
        
        // Follow alias if exists
        $stmt = $db->prepare("SELECT target_url FROM archive_aliases WHERE alias_url = ?");
        $stmt->execute([$url]);
        if ($row = $stmt->fetch()) {
            $url = $row['target_url'];
        }

        $normUrl = rtrim(strtolower($url), '/');

        // Insert directly into archive_queue
        $stmt = $db->prepare("INSERT INTO archive_queue (url, force_archive) VALUES (?, 1)");
        $stmt->execute([$normUrl]);

        // Redirect back to archive view
        $redirectUrl = '/archive?url=' . urlencode($url) . '&ts=' . time();
        header('Location: ' . $redirectUrl);
        exit;
    }
}
