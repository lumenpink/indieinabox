<?php

declare(strict_types=1);

namespace Indieinabox;

class WebRouter
{
    protected Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

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

        $isConfigParam = isset($_GET['config']);
        $isConfigPath = (preg_match('#/config$#i', $requestUriClean) === 1);

        if ($isConfigParam || $isConfigPath) {
            $handler = $this->createConfigHandler();
            $handler->handle();
            return;
        }

        $this->serveStatic();
    }

    protected function createWebmentionHandler(): WebmentionHandler
    {
        return new WebmentionHandler($this->site);
    }

    protected function createIndieAuthHandler(): IndieAuthHandler
    {
        return new IndieAuthHandler($this->site);
    }

    protected function createConfigHandler(): ConfigHandler
    {
        return new ConfigHandler($this->site);
    }

    protected function createMicropubHandler(): MicropubHandler
    {
        return new MicropubHandler($this->site);
    }

    protected function createMicropubClientHandler(): MicropubClientHandler
    {
        return new MicropubClientHandler($this->site);
    }

    private function serveStatic(): void
    {
        $base = $this->site->paths->baseDir;
        $outputDir = $this->site->paths->outputDir;
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Sanitize path to prevent directory traversal
        $path = str_replace(['..', '//'], ['', '/'], urldecode($requestUri));
        if ($path === '/') {
            $path = '/index.html';
        }

        $filePath = $base . DIRECTORY_SEPARATOR . $outputDir . $path;

        if (strpos($path, '/media/') === 0) {
            $contentMediaPath = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'content' . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (file_exists($contentMediaPath) && is_file($contentMediaPath)) {
                $filePath = $contentMediaPath;
            }
        }

        if (is_dir($filePath)) {
            $filePath = rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
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

        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 Not Found";
    }
}
