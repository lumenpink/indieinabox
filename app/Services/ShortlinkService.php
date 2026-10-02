<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Page\Page;

/**
 * Class ShortlinkService
 */
class ShortlinkService
{
    /**
     * @var string
     */
    private string $cacheDir;
    /**
     * @var callable|null
     */
    private $httpSender;

    /**
     * @param string|null $cacheDir
     * @param callable|null $httpSender Optional hook fn(string $server, array $postData, array $headers): string|array|null
     */
    public function __construct(?string $cacheDir = null, ?callable $httpSender = null)
    {
        $this->cacheDir = $cacheDir ?? (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'shortlinks');
        $this->httpSender = $httpSender;
    }

    /**
     * Gets a shortlink for a page, from cache or by requesting the server.
     *
     * @param Page $page
     * @param string $fqdn
     * @param array $config
     * @param bool $isDev
     *
     * @return false|string
     */
    public function getShortlink(Page $page, string $fqdn, array $config, bool $isDev = false): string|false
    {
        $url = rtrim($fqdn, '/') . '/' . ltrim($page->slug, '/');
        
        $localHash = rtrim($fqdn, '/') . '/s/' . substr(md5($url), 0, 8);

        if (empty($config['enabled']) || $isDev) {
            return $localHash;
        }

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }

        $cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . md5($url) . '.txt';

        if (is_file($cacheFile)) {
            $cached = trim((string) file_get_contents($cacheFile));
            if (!empty($cached)) {
                return $cached;
            }
        }

        $remote = $this->requestRemoteShortlink($url, $config);
        if ($remote !== null && !empty($remote)) {
            file_put_contents($cacheFile, $remote);
            return $remote;
        }

        return $localHash;
    }

    /**
     * Requests a remote shortlink from services like 0x0.st, 2lp.in, or Rustypaste.
     *
     * @param string $url Target URL to shorten
     * @param array $config Shortlink service configuration
     * @return string|null Shortlink URL on success, null on failure
     */
    public function requestRemoteShortlink(string $url, array $config): ?string
    {
        $server = trim((string) ($config['server'] ?? 'https://0x0.st'));
        $parameter = trim((string) ($config['parameter'] ?? 'shorten'));
        if (empty($server)) {
            return null;
        }

        $headers = [];
        if (!empty($config['auth_header']) && !empty($config['auth_token'])) {
            $headers[] = $config['auth_header'] . ': ' . $config['auth_token'];
        }

        if ($this->httpSender !== null) {
            $res = ($this->httpSender)($server, [$parameter => $url], $headers);
            if (is_string($res)) {
                $trimmed = trim($res);
                return (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) ? $trimmed : null;
            }
            if (is_array($res) && !empty($res['shortlink'])) {
                return trim((string) $res['shortlink']);
            }
            return null;
        }

        $postData = [$parameter => $url];
        $ch = curl_init($server);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Indieinabox ShortlinkBot/1.0 (+https://indieinabox.org)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (\PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        if ($httpCode >= 200 && $httpCode < 300 && is_string($response)) {
            $trimmed = trim($response);
            if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
                return $trimmed;
            }
        }

        return null;
    }
}
