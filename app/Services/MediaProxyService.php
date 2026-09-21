<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Override;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Services\Contracts\MediaProxyServiceInterface;

/**
 * Service managing external media proxying, cryptographic HMAC signatures,
 * SSRF prevention, and privacy caching for federated and syndication feeds.
 */
class MediaProxyService implements MediaProxyServiceInterface
{
    private string $secretKey;
    private string $cacheDir;
    private string $fqdn;
    private ?SettingsRepositoryInterface $settings;
    /** @var (callable(string, string): array{mime: string, size: int}|null)|null */
    private $customFetcher = null;

    /**
     * @param string|null $secretKey Explicit HMAC secret key (defaults to settings/database or auto-generated).
     * @param SettingsRepositoryInterface|null $settings Repository for configuration.
     * @param string|null $cacheDir Directory where media is cached.
     * @param string|null $fqdn Fully qualified domain name of the local instance.
     * @param (callable(string, string): array{mime: string, size: int}|null)|null $customFetcher Optional custom fetcher for testing.
     */
    public function __construct(
        ?string $secretKey = null,
        ?SettingsRepositoryInterface $settings = null,
        ?string $cacheDir = null,
        ?string $fqdn = null,
        ?callable $customFetcher = null
    ) {
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $this->settings = $settings ?? ($container && $container->has(SettingsRepositoryInterface::class)
            ? $container->get(SettingsRepositoryInterface::class)
            : null);

        $this->secretKey = $this->resolveSecretKey($secretKey);

        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data');
        $this->cacheDir = $cacheDir ?? ($dataDir . DIRECTORY_SEPARATOR . 'media_cache');
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }

        $resolvedFqdn = $fqdn ?? ($this->settings ? (string) $this->settings->get('fqdn') : '');
        $this->fqdn = rtrim($resolvedFqdn, '/');
        $this->customFetcher = $customFetcher;
    }

    /**
     * Resolves an HMAC secret key from explicit input, settings, or generates a secure fallback.
     */
    private function resolveSecretKey(?string $explicitKey): string
    {
        if ($explicitKey !== null && $explicitKey !== '') {
            return $explicitKey;
        }

        if ($this->settings !== null) {
            $configuredSecret = $this->settings->get('media_proxy_secret');
            if (is_string($configuredSecret) && $configuredSecret !== '') {
                return $configuredSecret;
            }

            $passwordHash = $this->settings->get('indieauth_password');
            if (is_string($passwordHash) && $passwordHash !== '') {
                return hash('sha256', $passwordHash);
            }

            $newSecret = bin2hex(random_bytes(32));
            try {
                $this->settings->set('media_proxy_secret', $newSecret);
            } catch (\Throwable) {
                // Non-critical fallback if repository is read-only in context
            }
            return $newSecret;
        }

        return hash('sha256', 'indieinabox_default_media_proxy_secret');
    }

    /**
     * Generates a signed proxy URL for an external media resource.
     */
    #[Override]
    public function generateProxiedUrl(string $url): string
    {
        $urlTrimmed = trim($url);
        if ($urlTrimmed === '' || filter_var($urlTrimmed, FILTER_VALIDATE_URL) === false) {
            return $url;
        }

        // Avoid proxying local media paths
        if (str_starts_with($urlTrimmed, '/media/') || str_starts_with($urlTrimmed, 'data:')) {
            return $url;
        }

        if ($this->fqdn !== '' && str_starts_with($urlTrimmed, $this->fqdn)) {
            return $url;
        }

        // Avoid double proxying
        if (str_contains($urlTrimmed, '/media/proxy?url=')) {
            return $url;
        }

        $sig = hash_hmac('sha256', $urlTrimmed, $this->secretKey);
        return '/media/proxy?url=' . rawurlencode($urlTrimmed) . '&sig=' . $sig;
    }

    /**
     * Verifies the cryptographic HMAC signature for a proxied media URL.
     */
    #[Override]
    public function verifySignature(string $url, string $signature): bool
    {
        if ($url === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $url, $this->secretKey);
        return hash_equals($expected, $signature);
    }

    /**
     * Validates whether an external URL is safe against SSRF attacks.
     */
    #[Override]
    public function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        $host = strtolower((string) $parts['host']);

        // Block obvious local names
        if (
            $host === 'localhost' ||
            $host === '127.0.0.1' ||
            $host === '::1' ||
            $host === '0.0.0.0' ||
            str_ends_with($host, '.local') ||
            str_ends_with($host, '.localhost') ||
            str_ends_with($host, '.internal')
        ) {
            return false;
        }

        // Disallow non-standard ports
        if (isset($parts['port'])) {
            $allowedPorts = [80, 443, 8080, 8443];
            if (!in_array((int) $parts['port'], $allowedPorts, true)) {
                return false;
            }
        }

        // Validate IP if host is an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        // Resolve DNS and validate each resolved IP
        $ips = @gethostbynamel($host);
        if ($ips === false || empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if an IP is public (not private, loopback, or reserved).
     */
    private function isPublicIp(string $ip): bool
    {
        $filtered = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($filtered === false) {
            return false;
        }

        // Additional sanity checks for IPv4 subnets
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = array_map('intval', explode('.', $ip));
            if (count($parts) === 4) {
                // 127.0.0.0/8 (Loopback)
                if ($parts[0] === 127) {
                    return false;
                }
                // 10.0.0.0/8 (Private)
                if ($parts[0] === 10) {
                    return false;
                }
                // 172.16.0.0/12 (Private)
                if ($parts[0] === 172 && $parts[1] >= 16 && $parts[1] <= 31) {
                    return false;
                }
                // 192.168.0.0/16 (Private)
                if ($parts[0] === 192 && $parts[1] === 168) {
                    return false;
                }
                // 169.254.0.0/16 (Link-Local)
                if ($parts[0] === 169 && $parts[1] === 254) {
                    return false;
                }
                // 0.0.0.0/8 (Current network)
                if ($parts[0] === 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns the local cached filesystem path for a media URL if it exists, or null.
     */
    #[Override]
    public function getCachedPath(string $url): ?string
    {
        $hash = hash('sha256', $url);
        $matches = glob($this->cacheDir . DIRECTORY_SEPARATOR . $hash . '.*');
        if ($matches) {
            foreach ($matches as $match) {
                if (str_ends_with($match, '.meta')) {
                    continue;
                }
                if (is_file($match) && filesize($match) > 0) {
                    return $match;
                }
            }
        }

        $directPath = $this->cacheDir . DIRECTORY_SEPARATOR . $hash;
        if (is_file($directPath) && filesize($directPath) > 0) {
            return $directPath;
        }

        return null;
    }

    /**
     * Fetches an external media resource securely, stores it in the local cache,
     * and returns metadata.
     *
     * @return array{path: string, mime: string, size: int}|null
     */
    #[Override]
    public function fetchAndCache(string $url): ?array
    {
        $cachedPath = $this->getCachedPath($url);
        if ($cachedPath !== null) {
            $metaPath = $this->getMetaPath($cachedPath);
            $mime = 'application/octet-stream';
            if (is_file($metaPath)) {
                $rawMeta = file_get_contents($metaPath);
                if ($rawMeta !== false) {
                    /** @var array{mime?: string} $meta */
                    $meta = (array) json_decode($rawMeta, true);
                    $mime = $meta['mime'] ?? $mime;
                }
            }
            if ($mime === 'application/octet-stream' && function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected = finfo_file($finfo, $cachedPath);
                    if (is_string($detected)) {
                        $mime = $detected;
                    }
                    finfo_close($finfo);
                }
            }

            return [
                'path' => $cachedPath,
                'mime' => $mime,
                'size' => (int) filesize($cachedPath),
            ];
        }

        if (!$this->isSafeUrl($url)) {
            return null;
        }

        $hash = hash('sha256', $url);
        $tempPath = $this->cacheDir . DIRECTORY_SEPARATOR . 'tmp_' . $hash . '_' . uniqid();

        if ($this->customFetcher !== null) {
            $customResult = ($this->customFetcher)($url, $tempPath);
            if ($customResult === null || !is_file($tempPath)) {
                @unlink($tempPath);
                return null;
            }
            $mime = $customResult['mime'];
        } else {
            $downloadSuccess = $this->downloadFileSafely($url, $tempPath);
            if (!$downloadSuccess || !is_file($tempPath) || filesize($tempPath) === 0) {
                @unlink($tempPath);
                return null;
            }

            $mime = 'application/octet-stream';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected = finfo_file($finfo, $tempPath);
                    if (is_string($detected)) {
                        $mime = $detected;
                    }
                    finfo_close($finfo);
                }
            }
        }

        // Validate MIME type is media
        if (!$this->isAllowedMime($mime)) {
            @unlink($tempPath);
            return null;
        }

        $ext = $this->mimeToExtension($mime);
        $finalPath = $this->cacheDir . DIRECTORY_SEPARATOR . $hash . ($ext !== '' ? '.' . $ext : '');

        if (!@rename($tempPath, $finalPath)) {
            @unlink($tempPath);
            return null;
        }

        $size = (int) filesize($finalPath);
        $metaData = [
            'url' => $url,
            'mime' => $mime,
            'size' => $size,
            'cached_at' => time(),
        ];
        @file_put_contents($finalPath . '.meta', json_encode($metaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'path' => $finalPath,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    /**
     * Rewrites media elements in HTML content to use signed proxy URLs.
     */
    #[Override]
    public function rewriteHtmlMedia(string $html): string
    {
        $transformed = (string) preg_replace_callback(
            '/<(img|video|audio|source)([^>]*?)src=([\'"])([^\'"]+)\3([^>]*?)>/i',
            function (array $matches): string {
                $tag = (string) $matches[1];
                $before = (string) $matches[2];
                $quote = (string) $matches[3];
                $src = (string) $matches[4];
                $after = (string) $matches[5];

                $proxied = $this->generateProxiedUrl($src);
                return "<{$tag}{$before}src={$quote}{$proxied}{$quote}{$after}>";
            },
            $html
        );

        // Also rewrite video poster attributes
        return (string) preg_replace_callback(
            '/<video([^>]*?)poster=([\'"])([^\'"]+)\2([^>]*?)>/i',
            function (array $matches): string {
                $before = (string) $matches[1];
                $quote = (string) $matches[2];
                $poster = (string) $matches[3];
                $after = (string) $matches[4];

                $proxied = $this->generateProxiedUrl($poster);
                return "<video{$before}poster={$quote}{$proxied}{$quote}{$after}>";
            },
            $transformed
        );
    }

    /**
     * Downloads an external URL safely with timeout, redirection validation, and size capping.
     */
    protected function downloadFileSafely(string $url, string $destPath): bool
    {
        if (!extension_loaded('curl')) {
            return false;
        }

        $fp = @fopen($destPath, 'wb');
        if (!$fp) {
            return false;
        }

        $maxSizeMB = 15.0;
        if ($this->settings) {
            $settingSize = $this->settings->get('media_proxy_max_size_mb');
            if ($settingSize !== null && $settingSize !== '') {
                $maxSizeMB = (float) $settingSize;
            }
        }
        $maxSizeBytes = (int) ($maxSizeMB * 1024.0 * 1024.0);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'IndieInABox Media Proxy/1.0');

        if ($maxSizeBytes > 0) {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($ch, $downloadSize, $downloaded) use ($maxSizeBytes): int {
                if ($downloadSize > $maxSizeBytes || $downloaded > $maxSizeBytes) {
                    return 1; // Aborts transfer
                }
                return 0;
            });
        }

        $success = curl_exec($ch);
        $error = curl_errno($ch);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        fclose($fp);

        if ($success === false || $error !== 0) {
            @unlink($destPath);
            return false;
        }

        // Prevent SSRF via redirects
        if ($effectiveUrl !== '' && !$this->isSafeUrl($effectiveUrl)) {
            @unlink($destPath);
            return false;
        }

        return true;
    }

    /**
     * Determines whether a MIME type is acceptable media.
     */
    private function isAllowedMime(string $mime): bool
    {
        $allowedPrefixes = ['image/', 'video/', 'audio/'];
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($mime, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Maps common media MIME types to canonical file extensions.
     */
    private function mimeToExtension(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/avif' => 'avif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
        ];

        return $map[$mime] ?? '';
    }

    /**
     * Returns the metadata sidecar path for a cached media file.
     */
    private function getMetaPath(string $filePath): string
    {
        return $filePath . '.meta';
    }
}
