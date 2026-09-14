<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;
use Indieinabox\Webmention\LinkExtractor;

/**
 * Class WebmentionSender
 *
 * Scans newly published or modified content for outbound mentions and queues them for delivery.
 */
class WebmentionSender
{
    /**
     * Extracts outgoing links and queues them for webmention sending.
     *
     * @param string $sourceUrl The URL of the post we just created.
     * @param array<string, mixed> $frontmatter The frontmatter of the post.
     * @param string $content The Markdown or HTML content of the post.
     * @param ?PDO $db Optional database connection.
     * @return void
     */
    public static function queueOutgoingWebmentions(
        string $sourceUrl,
        array $frontmatter,
        string $content,
        ?PDO $db = null
    ): void {
        $settings = Database::getAllSettings();
        if (empty($settings['webmention_enabled'])) {
            return;
        }

        $links = LinkExtractor::extractLinks($sourceUrl, $frontmatter, $content);
        if (empty($links)) {
            return;
        }

        $database = $db ?? Database::getDb();
        $stmt = $database->prepare(
            'INSERT INTO outgoing_webmentions (source_url, target_url, created_at) VALUES (:source, :target, :time)'
        );

        $now = time();
        foreach ($links as $targetUrl) {
            // Check if we already queued this exact pair recently to prevent spam
            $check = $database->prepare(
                'SELECT id FROM outgoing_webmentions WHERE source_url = ? AND target_url = ?'
            );
            $check->execute([$sourceUrl, $targetUrl]);
            if ($check->fetch()) {
                continue;
            }

            $stmt->bindValue(':source', $sourceUrl);
            $stmt->bindValue(':target', $targetUrl);
            $stmt->bindValue(':time', $now);
            $stmt->execute();
        }
    }

    /**
     * Discovers a Webmention endpoint for a target URL according to W3C specification.
     *
     * @param string $targetUrl The target URL to discover an endpoint for.
     * @return string|null The discovered endpoint URL, or null if not found.
     */
    public static function discoverEndpoint(string $targetUrl): ?string
    {
        $details = self::discoverEndpointDetails($targetUrl);
        return $details['endpoint'];
    }

    /**
     * Discovers Webmention endpoint details including discovery method and response code.
     *
     * @param string $targetUrl
     * @return array{target: string, effective_url: string, http_code: int, endpoint: ?string, method: ?string}
     */
    public static function discoverEndpointDetails(string $targetUrl): array
    {
        $result = [
            'target' => $targetUrl,
            'effective_url' => $targetUrl,
            'http_code' => 0,
            'endpoint' => null,
            'method' => null,
        ];

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Indieinabox Webmention Discoverer/1.0 (+https://indieinabox.org)',
            'Accept: text/html, application/xhtml+xml, */*',
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return $result;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        $result['http_code'] = $httpCode;
        $result['effective_url'] = $effectiveUrl !== '' ? $effectiveUrl : $targetUrl;

        // 1. Check HTTP Link headers (Priority 1 per W3C specification)
        $headerEndpoint = self::parseHeaderEndpoint($headers, $result['effective_url']);
        if ($headerEndpoint !== null) {
            $result['endpoint'] = $headerEndpoint;
            $result['method'] = 'http_header';
            return $result;
        }

        // 2. Check HTML body (<link> or <a> in document order)
        $htmlEndpoint = self::parseHtmlEndpoint($body, $result['effective_url']);
        if ($htmlEndpoint !== null) {
            $result['endpoint'] = $htmlEndpoint['endpoint'];
            $result['method'] = $htmlEndpoint['tag'] === 'link' ? 'html_link' : 'html_a';
            return $result;
        }

        return $result;
    }

    /**
     * Parses HTTP Link headers looking for rel="webmention".
     *
     * @param string $headers Raw HTTP response headers.
     * @param string $effectiveUrl Base URL for resolving relative links.
     * @return string|null Resolved endpoint URL, or null if not found.
     */
    public static function parseHeaderEndpoint(string $headers, string $effectiveUrl): ?string
    {
        if (!preg_match_all('/^Link:\s*(.+)$/im', $headers, $matches)) {
            return null;
        }

        foreach ($matches[1] as $headerLine) {
            $parts = explode(',', $headerLine);
            foreach ($parts as $part) {
                if (preg_match('/<([^>]+)>([^,]*)/', trim($part), $linkMatch)) {
                    $rawUrl = trim($linkMatch[1]);
                    $params = $linkMatch[2];
                    if (preg_match('/(?:^|;)\s*rel\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^;\s]+))/i', $params, $relMatch)) {
                        $relVal = $relMatch[1] !== '' ? $relMatch[1] : ($relMatch[2] !== '' ? $relMatch[2] : ($relMatch[3] ?? ''));
                        $rels = preg_split('/\s+/', strtolower(trim($relVal))) ?: [];
                        if (in_array('webmention', $rels, true) || in_array('http://webmention.org/', $rels, true) || in_array('https://webmention.org/', $rels, true)) {
                            return self::resolveUrl($effectiveUrl, $rawUrl);
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Parses HTML body for <link> or <a> tags with rel="webmention" in document order.
     *
     * @param string $html
     * @param string $effectiveUrl Base URL for resolving relative links.
     * @return array{endpoint: string, tag: string}|null
     */
    public static function parseHtmlEndpoint(string $html, string $effectiveUrl): ?array
    {
        if (trim($html) === '') {
            return null;
        }

        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($dom);
        $elements = $xpath->query('//*[(self::link or self::a) and @rel and @href]');
        if ($elements === false) {
            return null;
        }

        foreach ($elements as $el) {
            /** @var \DOMElement $el */
            $relAttr = $el->getAttribute('rel');
            $rels = preg_split('/\s+/', strtolower(trim($relAttr))) ?: [];
            if (in_array('webmention', $rels, true) || in_array('http://webmention.org/', $rels, true) || in_array('https://webmention.org/', $rels, true)) {
                $href = trim($el->getAttribute('href'));
                if ($href !== '') {
                    return [
                        'endpoint' => self::resolveUrl($effectiveUrl, $href),
                        'tag' => strtolower($el->tagName),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Sends a Webmention ping to the discovered endpoint.
     *
     * @param string $endpoint The Webmention endpoint URL.
     * @param string $source The source URL of the mentioning post.
     * @param string $target The target URL being mentioned.
     * @return array{http_code: int, success: bool, response: string, error: ?string}
     */
    public static function sendWebmention(string $endpoint, string $source, string $target): array
    {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'source' => $source,
            'target' => $target,
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Indieinabox Webmention Sender/1.0 (+https://indieinabox.org)',
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json, text/html, */*',
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch) ?: null;
        curl_close($ch);

        $success = ($httpCode >= 200 && $httpCode < 300);

        return [
            'http_code' => $httpCode,
            'success' => $success,
            'response' => (string) $response,
            'error' => $error,
        ];
    }

    /**
     * Resolves a relative URL against a base URL according to RFC 3986.
     *
     * @param string $base Base URL.
     * @param string $rel Relative or absolute target URL.
     * @return string Fully-qualified resolved URL.
     */
    public static function resolveUrl(string $base, string $rel): string
    {
        if ($rel === '') {
            return $base;
        }

        $relParts = parse_url($rel);
        if ($relParts === false) {
            return $rel;
        }

        if (!empty($relParts['scheme'])) {
            return $rel;
        }

        $baseParts = parse_url($base);
        if ($baseParts === false || empty($baseParts['host'])) {
            return $rel;
        }

        $scheme = $baseParts['scheme'] ?? 'http';
        $host = $baseParts['host'];
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        $user = $baseParts['user'] ?? '';
        $pass = isset($baseParts['pass']) ? ':' . $baseParts['pass'] : '';
        $userInfo = $user !== '' ? $user . $pass . '@' : '';
        $authority = $userInfo . $host . $port;

        // Protocol-relative URL
        if (strpos($rel, '//') === 0) {
            return $scheme . ':' . $rel;
        }

        // Root-relative URL
        if ($rel[0] === '/') {
            return $scheme . '://' . $authority . $rel;
        }

        // Query or fragment only
        if ($rel[0] === '?' || $rel[0] === '#') {
            $baseWithoutQuery = strtok($base, '?#');
            return $baseWithoutQuery . $rel;
        }

        // Relative path
        $basePath = $baseParts['path'] ?? '/';
        if ($basePath === '' || substr($basePath, -1) !== '/') {
            $basePath = dirname($basePath);
            if ($basePath === '\\' || $basePath === '.') {
                $basePath = '/';
            } else {
                $basePath = rtrim($basePath, '/') . '/';
            }
        }

        $combinedPath = $basePath . $rel;
        $segments = explode('/', $combinedPath);
        $normalized = [];
        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($normalized);
            } elseif ($segment !== '.' && $segment !== '') {
                $normalized[] = $segment;
            }
        }

        $leadingSlash = strpos($combinedPath, '/') === 0 ? '/' : '';
        $trailingSlash = substr($combinedPath, -1) === '/' ? '/' : '';

        return $scheme . '://' . $authority . $leadingSlash . implode('/', $normalized) . ($trailingSlash !== '' && count($normalized) > 0 ? '/' : '');
    }
}
