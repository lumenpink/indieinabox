<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class ShortlinkManager
 */
class ShortlinkManager
{
    /**
     * @var string
     */
    private string $cacheDir;

    /**
     * @param string|null $cacheDir
     */
    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'shortlinks');
    }

    /**
     * Gets a shortlink for a page, from cache or by requesting the server.
     *
     * @param Page $page
     * @param string $fqdn
     * @param array $config
     * @return string|null
     */
    public function getShortlink(Page $page, string $fqdn, array $config): ?string
    {
        if (empty($config['enabled'])) {
            return null;
        }

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }

        $url = rtrim($fqdn, '/') . '/' . ltrim($page->slug, '/');
        $cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . md5($url) . '.txt';

        if (is_file($cacheFile)) {
            return file_get_contents($cacheFile);
        }

        $server = $config['server'] ?? 'https://0x0.st';
        $parameter = $config['parameter'] ?? 'shorten';
        
        // Prepare multipart/form-data for the POST request
        $boundary = self::generateBoundary(24);
        $content = "--" . $boundary . "\r\n";
        $content .= "Content-Disposition: form-data; name=\"" . $parameter . "\"\r\n\r\n";
        $content .= $url . "\r\n";
        $content .= "--" . $boundary . "--\r\n";

        $headers = [
            "Content-Type: multipart/form-data; boundary=" . $boundary,
            "User-Agent: Indieinabox/1.0 (Shortlink Fetcher)"
        ];

        if (!empty($config['auth_header']) && !empty($config['auth_token'])) {
            $headers[] = $config['auth_header'] . ": " . $config['auth_token'];
        }

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $content,
                'timeout' => 5.0
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($server, false, $context);

        if ($response !== false) {
            $shortlink = trim($response);
            if (filter_var($shortlink, FILTER_VALIDATE_URL)) {
                file_put_contents($cacheFile, $shortlink);
                return $shortlink;
            }
        }

        return null; // Return null if it failed
    }

    private static function generateBoundary(int $length = 24): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $boundary = '';
        for ($i = 0; $i < $length; $i++) {
            $boundary .= substr($chars, rand(0, strlen($chars) - 1), 1);
        }
        return $boundary;
    }
}
