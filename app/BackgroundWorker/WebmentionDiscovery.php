<?php

declare(strict_types=1);

namespace Indieinabox\BackgroundWorker;

use Indieinabox\Site;
use PDO;

/**
 * Class WebmentionDiscovery
 *
 * Scans remote domains queued in webmention_discovery_cache to determine
 * if they support receiving Webmentions.
 */
class WebmentionDiscovery
{
    private Site $site;
    private PDO $db;
    /**
     * @var array<string, callable>
     */
    private array $callbacks;

    /**
     * @param Site $site
     * @param PDO $db
     * @param array<string, callable> $callbacks Optional HTTP fetcher hooks
     */
    public function __construct(Site $site, PDO $db, array $callbacks = [])
    {
        $this->site = $site;
        $this->db = $db;
        $this->callbacks = $callbacks;
    }

    /**
     * Discovers Webmention support for queued domains.
     *
     * @return void
     */
    public function process(): void
    {
        echo "Running Webmention Discovery...\n";

        $sql = "SELECT domain FROM webmention_discovery_cache WHERE last_checked = 0 OR last_checked < ? ORDER BY last_checked ASC LIMIT 10";
        // Check unknown, or recheck domains older than 7 days
        $threshold = time() - (7 * 86400);
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$threshold]);
        $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($domains)) {
            echo "No domains to discover.\n";
            return;
        }

        $stmtUpdate = $this->db->prepare(
            "UPDATE webmention_discovery_cache SET supports_webmention = ?, last_checked = ? WHERE domain = ?"
        );

        foreach ($domains as $row) {
            $domain = (string) $row['domain'];
            $url = "https://" . $domain . "/";
            echo "Checking {$url} for webmention support...\n";

            $supports = 0;
            $html = $this->fetchUrl($url);

            if ($html !== false && !empty($html)) {
                // Check headers first (if available in global $http_response_header)
                $headers = $GLOBALS['http_response_header'] ?? [];
                foreach ($headers as $header) {
                    if (stripos($header, 'rel="webmention"') !== false || stripos($header, 'rel=webmention') !== false) {
                        $supports = 1;
                        break;
                    }
                }

                // If not found in headers, check HTML
                if ($supports === 0 && preg_match('/<link\s+[^>]*rel=[\'"]?(?:[^>]*\s+)?webmention(?:\s+[^>]*)?[\'"]?[^>]*>/i', (string) $html)) {
                    $supports = 1;
                }
            }

            $stmtUpdate->execute([$supports, time(), $domain]);
        }

        echo "Webmention Discovery done.\n";
    }

    /**
     * Fetches URL content using callback or standard stream context.
     *
     * @param string $url
     * @return string|false
     */
    protected function fetchUrl(string $url): string|false
    {
        if (isset($this->callbacks['fetchUrl'])) {
            return ($this->callbacks['fetchUrl'])($url);
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 5,
                'header' => "User-Agent: Indieinabox Webmention Discovery\r\n"
            ]
        ]);
        return @file_get_contents($url, false, $ctx);
    }
}
