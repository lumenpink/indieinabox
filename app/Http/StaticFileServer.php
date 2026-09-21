<?php

declare(strict_types=1);

namespace Indieinabox\Http;

use Indieinabox\Site\Site;

/**
 * Serves static assets, compiled HTML, and media files from site output directories,
 * with content negotiation for ActivityPub representations.
 */
class StaticFileServer
{
    private Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Attempts to serve static files from the output directory based on the request URI.
     */
    public function serve(?string $requestUri = null): void
    {
        $uri = $requestUri ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
        $outputDir = $this->site->paths->outputDirHtml;
        $path = str_replace(['..', '//'], ['', '/'], urldecode($uri));

        $isRoot = ($path === '' || $path === '/' || $path === '/index.html');
        if ($path === '' || $path === '/') {
            $path = '/index.html';
        }

        $base = rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR);
        $filePath = $base . DIRECTORY_SEPARATOR . $outputDir . $path;

        if (str_starts_with($path, '/media/')) {
            $contentMediaPath = rtrim($this->site->paths->getContentPath(), DIRECTORY_SEPARATOR);
            $contentMediaPath .= str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (file_exists($contentMediaPath) && is_file($contentMediaPath)) {
                $filePath = $contentMediaPath;
            }
        }

        if (is_dir($filePath)) {
            $filePath = rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
        }

        // On-demand build for site root if index.html is missing (#8)
        if ($isRoot && !file_exists($filePath)) {
            if (class_exists(\Indieinabox\SiteBuilder\SiteBuilder::class)) {
                $builder = new \Indieinabox\SiteBuilder\SiteBuilder($this->site);
                $builder->build();
            }
        }

        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $acceptsAP = (
            str_contains($acceptHeader, 'application/activity+json') ||
            str_contains($acceptHeader, 'application/ld+json')
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

        $this->renderNotFound($filePath, $base, $outputDir);
    }

    /**
     * Renders a customized, stylish 404 Not Found response.
     */
    protected function renderNotFound(string $filePath, string $base, string $outputDir): void
    {
        header('HTTP/1.1 404 Not Found');

        // 1. Static compiled 404.html in output directory
        $custom404 = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . '404.html';
        if (file_exists($custom404) && is_file($custom404)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($custom404);
            return;
        }

        // 2. Theme view 404
        if (class_exists(\Indieinabox\Theme\ThemeManager::class) && \Indieinabox\Theme\ThemeManager::hasView('404')) {
            header('Content-Type: text/html; charset=utf-8');
            echo \Indieinabox\Theme\ThemeManager::renderView('404', ['site' => $this->site, 'path' => $filePath]);
            return;
        }

        // 3. Polished fallback HTML 404
        header('Content-Type: text/html; charset=utf-8');
        $sitename = htmlspecialchars($this->site->metadata->sitename ?? 'Indie In A Box', ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found | {$sitename}</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-pink: #f43f5e;
            --accent-cyan: #38bdf8;
            --border-color: #334155;
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg-color: #f8fafc;
                --card-bg: #ffffff;
                --text-main: #0f172a;
                --text-muted: #64748b;
                --accent-pink: #e11d48;
                --accent-cyan: #0284c7;
                --border-color: #e2e8f0;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .error-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            max-width: 480px;
            width: 100%;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .error-code {
            font-size: 5rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -2px;
            background: linear-gradient(135deg, var(--accent-pink), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 2rem;
        }
        .btn-home {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent-pink), var(--accent-cyan));
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .btn-home:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <main class="error-card">
        <div class="error-code">404</div>
        <h1>404 Not Found</h1>
        <p>The page or resource you are looking for does not exist or has moved.</p>
        <a href="/" class="btn-home">&larr; Return to {$sitename}</a>
    </main>
</body>
</html>
HTML;
    }

    /**
     * Resolves the MIME content-type for a file extension.
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
