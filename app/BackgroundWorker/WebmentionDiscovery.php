<?php

declare(strict_types=1);

namespace Indieinabox\BackgroundWorker;

use Indieinabox\Site\Site;
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
     * @var callable|null
     */
    private $fetcher;

    /**
     * @param Site $site
     * @param PDO $db
     * @param callable|null $fetcher Optional HTTP fetcher hook fn(string $url): string|false
     */
    public function __construct(Site $site, PDO $db, ?callable $fetcher = null)
    {
        $this->site = $site;
        $this->db = $db;
        $this->fetcher = $fetcher;
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

            if ($html) {
                // Check headers first (if we had access to $http_response_header)
                $headers = $http_response_header ?? [];
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
     * Fetches remote content over HTTP.
     *
     * @param string $url
     * @return string|false
     */
    public function fetchUrl(string $url)
    {
        if ($this->fetcher !== null) {
            return ($this->fetcher)($url);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Indieinabox WebmentionDiscovery/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
}
