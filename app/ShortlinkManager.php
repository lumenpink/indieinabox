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
    public function getShortlink(Page $page, string $fqdn, array $config, bool $isDev = false): ?string
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
            return file_get_contents($cacheFile);
        }

        // Network operation is deferred to the cron worker. 
        // During the static build, we return the local hash if not cached.
        return $localHash;
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
