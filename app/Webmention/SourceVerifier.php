<?php

declare(strict_types=1);

namespace Indieinabox\Webmention;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Indieinabox\Whostyles;

/**
 * Class SourceVerifier
 *
 * Verifies that a remote source page links back to a local target URL,
 * and extracts microformats / metadata (title, e-content, Whostyles).
 */
class SourceVerifier
{
    /**
     * @var callable|null Custom URL fetcher callable.
     */
    private $fetcher;

    /**
     * SourceVerifier constructor.
     *
     * @param ?callable $fetcher Optional callback to fetch remote URLs: fn(string $url): string|false.
     */
    public function __construct(?callable $fetcher = null)
    {
        $this->fetcher = $fetcher;
    }

    /**
     * Verifies that the source URL contains a link to target URL and extracts metadata.
     *
     * @param string $source
     * @param string $target
     * @return array{success: bool, message?: string, content?: array{title: string, text: string, whostyle?: array<array-key, mixed>|null}}
     */
    public function verifySourceLink(string $source, string $target): array
    {
        $html = $this->fetchUrl($source);

        if ($html === false) {
            return [
                'success' => false,
                'message' => 'Unable to fetch source URL.'
            ];
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $links = $xpath->query('//a[@href]');
        $found = false;
        foreach ($links as $link) {
            if ($link instanceof DOMElement) {
                $href = $link->getAttribute('href');
                if ($this->urlsMatch($href, $target, $source)) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            return [
                'success' => false,
                'message' => 'No link to target URL found on source page.'
            ];
        }

        $titleNode = $xpath->query('//title')->item(0);
        $title = $titleNode ? trim($titleNode->nodeValue) : '';

        $content = '';
        $entryContent = $xpath->query('//*[contains(@class, "e-content")]')->item(0);
        if ($entryContent) {
            $content = trim($entryContent->nodeValue);
        } else {
            $pNode = $xpath->query('//p')->item(0);
            if ($pNode) {
                $content = trim($pNode->nodeValue);
            }
        }

        if (strlen($content) > 300) {
            $content = substr($content, 0, 297) . '...';
        }

        // Extract Whostyles V2 Hash
        $whostyleData = null;
        $hash = Whostyles::extract($html);
        if ($hash) {
            $whostyleData = Whostyles::decode($hash);
        }

        return [
            'success' => true,
            'content' => [
                'title' => $title,
                'text' => $content,
                'whostyle' => $whostyleData
            ]
        ];
    }

    /**
     * Compares target and link href to check if they match, including relative links.
     *
     * @param string $href
     * @param string $target
     * @param string $source
     * @return bool
     */
    public function urlsMatch(string $href, string $target, string $source): bool
    {
        $targetNorm = $this->normalizeUrl($target);

        if (strncasecmp($href, 'http', 4) === 0) {
            return strcasecmp($this->normalizeUrl($href), $targetNorm) === 0;
        }

        // Relative URL resolution
        $sourceParts = parse_url($source);
        $base = ($sourceParts['scheme'] ?? 'http') . '://' . ($sourceParts['host'] ?? '');
        if (isset($sourceParts['port'])) {
            $base .= ':' . $sourceParts['port'];
        }

        if (str_starts_with($href, '/')) {
            $resolved = $base . '/' . ltrim($href, '/');
        } else {
            $path = $sourceParts['path'] ?? '/';
            $dir = dirname($path);
            $dirClean = ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : trim($dir, '/');
            if ($dirClean !== '') {
                $resolved = $base . '/' . $dirClean . '/' . ltrim($href, '/');
            } else {
                $resolved = $base . '/' . ltrim($href, '/');
            }
        }

        return strcasecmp($this->normalizeUrl($resolved), $targetNorm) === 0;
    }

    /**
     * Normalizes a URL for canonical matching (resolves path segments and trailing slashes).
     *
     * @param string $url
     * @return string
     */
    public function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!$parts) {
            return $url;
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'http';
        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';

        // Resolve relative path segments like .. and .
        $segments = explode('/', $path);
        $resolved = [];
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $segment;
            }
        }

        $path = '/' . implode('/', $resolved);
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return $scheme . '://' . $host . $port . $path . $query;
    }

    /**
     * Fetches remote URL content.
     *
     * @param string $url
     * @return string|false
     */
    public function fetchUrl(string $url): string|false
    {
        if ($this->fetcher !== null) {
            return ($this->fetcher)($url);
        }

        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: IndieinaboxWebmentionReceiver/0.1.0\r\nAccept: text/html\r\n",
                'timeout' => 5,
            ]
        ];
        $context = stream_context_create($options);
        return @file_get_contents($url, false, $context);
    }
}
