<?php

declare(strict_types=1);

namespace Indieinabox\Http;

use Indieinabox\Site;

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

        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 Not Found. File path checked: " . $filePath;
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
