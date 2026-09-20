<?php

declare(strict_types=1);

namespace Indieinabox\BackgroundWorker;

use Indieinabox\Core\Database;
use Indieinabox\Site\Site;
use PDO;

/**
 * Class ArchiveProcessor
 *
 * Processes pending items in archive_queue, sending target links to the Wayback Machine
 * and generating local PDF snapshots via the Microlink API.
 */
class ArchiveProcessor
{
    private Site $site;
    private PDO $db;
    /**
     * @var callable|null
     */
    private $urlResolver;
    /**
     * @var callable|null
     */
    private $archiveOrgSender;
    /**
     * @var callable|null
     */
    private $pdfFetcher;
    /**
     * @var callable|null
     */
    private $fetcher;

    /**
     * @param Site $site
     * @param PDO $db
     * @param callable|null $urlResolver Optional hook fn(string $url): string
     * @param callable|null $archiveOrgSender Optional hook fn(string $url): void
     * @param callable|null $pdfFetcher Optional hook fn(string $url, string $normUrl, string $pdfDir): ?string
     * @param callable|null $fetcher Optional hook fn(string $url): string|false
     */
    public function __construct(
        Site $site,
        PDO $db,
        ?callable $urlResolver = null,
        ?callable $archiveOrgSender = null,
        ?callable $pdfFetcher = null,
        ?callable $fetcher = null
    ) {
        $this->site = $site;
        $this->db = $db;
        $this->urlResolver = $urlResolver;
        $this->archiveOrgSender = $archiveOrgSender;
        $this->pdfFetcher = $pdfFetcher;
        $this->fetcher = $fetcher;
    }

    /**
     * Processes the archive queue.
     *
     * @return void
     */
    public function process(): void
    {
        echo "Running Archive Queue processor...\n";

        $sql = "SELECT id, url, requested_at, force_archive FROM archive_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 10";
        $stmt = $this->db->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            echo "No archive items.\n";
            return;
        }

        $dataDir = Database::$dataDir ?? (dirname(__DIR__, 2) . '/data');
        $pdfDir = $dataDir . DIRECTORY_SEPARATOR . 'archives';
        if (!is_dir($pdfDir)) {
            @mkdir($pdfDir, 0755, true);
        }

        foreach ($items as $item) {
            $id = $item['id'];
            $url = (string) $item['url'];
            $requestedAt = (int) $item['requested_at'];
            $forceArchive = (int) ($item['force_archive'] ?? 0);

            // Mark as processing
            $this->db->prepare("UPDATE archive_queue SET status = 'processing' WHERE id = ?")->execute([$id]);

            echo "Archiving URL: $url\n";

            // Resolve final URL following redirects
            $finalUrl = $this->resolveFinalUrl($url);
            if ($finalUrl !== $url) {
                // Save to archive_aliases
                $stmtAlias = $this->db->prepare("INSERT OR IGNORE INTO archive_aliases (original_url, final_url) VALUES (?, ?)");
                $stmtAlias->execute([$url, $finalUrl]);
                $url = $finalUrl;
            }

            // Basic normalization (strip trailing slash, to lowercase)
            $normUrl = rtrim(strtolower($url), '/');

            if (!$forceArchive) {
                // Check if already archived recently
                $stmtCheck = $this->db->prepare("SELECT * FROM archived_links WHERE url = ? AND timestamp > ?");
                $stmtCheck->execute([$normUrl, $requestedAt - 86400]);
                if ($stmtCheck->fetch()) {
                    // Skip
                    $this->db->prepare("DELETE FROM archive_queue WHERE id = ?")->execute([$id]);
                    continue;
                }
            }

            // 1. Send to Archive.org (fire and forget)
            $this->sendToArchiveOrg($url);

            // 2. Generate PDF via Microlink API
            $localPdfPath = $this->fetchPdfFromMicrolink($url, $normUrl, $pdfDir);

            // Insert into archived_links
            $stmtInsert = $this->db->prepare(
                "INSERT INTO archived_links (url, timestamp, local_pdf_path, archive_org_url) VALUES (?, ?, ?, ?)"
            );
            $stmtInsert->execute([
                $normUrl,
                time(),
                $localPdfPath,
                "https://web.archive.org/web/" . gmdate('YmdHis') . "/" . $url
            ]);

            // Mark as done
            $this->db->prepare("DELETE FROM archive_queue WHERE id = ?")->execute([$id]);
            echo "Archived: $url\n";
        }

        echo "Archive queue done.\n";
    }

    /**
     * Resolves final destination URL following HTTP redirects.
     *
     * @param string $url
     * @return string
     */
    public function resolveFinalUrl(string $url): string
    {
        if ($this->urlResolver !== null) {
            return ($this->urlResolver)($url);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Indieinabox ArchiveBot/1.0 (+https://indieinabox.org)');
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return $finalUrl ?: $url;
    }

    /**
     * Submits a URL to the Wayback Machine save endpoint.
     *
     * @param string $url
     * @return void
     */
    public function sendToArchiveOrg(string $url): void
    {
        if ($this->archiveOrgSender !== null) {
            ($this->archiveOrgSender)($url);
            return;
        }

        $saveEndpoint = 'https://web.archive.org/save/' . $url;
        $ch = curl_init($saveEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Indieinabox ArchiveBot/1.0 (+https://indieinabox.org)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Fetches a PDF snapshot from the Microlink API.
     *
     * @param string $url
     * @param string $normUrl
     * @param string $pdfDir
     * @return string|null
     */
    public function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string
    {
        if ($this->pdfFetcher !== null) {
            return ($this->pdfFetcher)($url, $normUrl, $pdfDir);
        }

        $apiUrl = 'https://api.microlink.io?url=' . urlencode($url) . '&pdf=true';
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $res) {
            $data = json_decode($res, true);
            $pdfRemoteUrl = $data['data']['pdf']['url'] ?? null;
            if ($pdfRemoteUrl) {
                $pdfData = $this->fetchUrl($pdfRemoteUrl);
                if ($pdfData) {
                    $filename = md5($normUrl . time()) . '.pdf';
                    $filepath = $pdfDir . DIRECTORY_SEPARATOR . $filename;
                    if (file_put_contents($filepath, $pdfData) !== false) {
                        return '/data/archives/' . $filename;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Fetches remote content over HTTP.
     *
     * @param string $url
     * @return string|bool
     */
    public function fetchUrl(string $url): string|bool
    {
        if ($this->fetcher !== null) {
            return ($this->fetcher)($url);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
}
