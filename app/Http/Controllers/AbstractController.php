<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\Site;

/**
 * Base HTTP Controller providing standard request handling and response emission methods.
 */
abstract class AbstractController
{
    protected Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Emits a JSON response.
     *
     * @param mixed $data
     * @param int $status
     * @param array<string, string> $headers
     */
    protected function json(mixed $data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $k => $v) {
            header("{$k}: {$v}");
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Emits an HTML response.
     *
     * @param string $html
     * @param int $status
     * @param array<string, string> $headers
     */
    protected function html(string $html, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        foreach ($headers as $k => $v) {
            header("{$k}: {$v}");
        }
        echo $html;
    }

    /**
     * Emits a redirect header.
     */
    protected function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header("Location: {$url}");
    }

    /**
     * Sets HTTP status code.
     */
    protected function status(int $status): void
    {
        http_response_code($status);
    }
}
