<?php

declare(strict_types=1);

namespace Indieinabox\BackgroundWorker;

use Indieinabox\Site;
use Indieinabox\Database;
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
     * @var array<string, callable>
     */
    private array $callbacks;

    /**
     * @param Site $site
     * @param PDO $db
     * @param array<string, callable> $callbacks
     */
    public function __construct(Site $site, PDO $db, array $callbacks = [])
    {
        $this->site = $site;
        $this->db = $db;
        $this->callbacks = $callbacks;
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
        if (isset($this->callbacks['resolveFinalUrl'])) {
            return ($this->callbacks['resolveFinalUrl'])($url);
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
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return is_string($finalUrl) && $finalUrl !== '' ? $finalUrl : $url;
    }

    /**
     * Submits a URL to the Wayback Machine.
     *
     * @param string $url
     * @return void
     */
    public function sendToArchiveOrg(string $url): void
    {
        if (isset($this->callbacks['sendToArchiveOrg'])) {
            ($this->callbacks['sendToArchiveOrg'])($url);
            return;
        }

        $archiveOrgUrl = "https://web.archive.org/save/" . $url;
        $ch = curl_init($archiveOrgUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Indieinabox WebArchiver']);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Generates and downloads a PDF snapshot of a URL via Microlink API.
     *
     * @param string $url
     * @param string $normUrl
     * @param string $pdfDir
     * @return string|null
     */
    public function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string
    {
        if (isset($this->callbacks['fetchPdfFromMicrolink'])) {
            return ($this->callbacks['fetchPdfFromMicrolink'])($url, $normUrl, $pdfDir);
        }

        $pdfApiUrl = "https://api.microlink.io/?url=" . urlencode($url) . "&pdf=true&meta=false";
        $pdfData = $this->fetchJsonUrl($pdfApiUrl);

        if ($pdfData && isset($pdfData['data']['pdf']['url'])) {
            $pdfDownloadUrl = (string) $pdfData['data']['pdf']['url'];
            $pdfBytes = $this->fetchUrl($pdfDownloadUrl);
            if ($pdfBytes !== false && !empty($pdfBytes)) {
                $filename = md5($normUrl . time()) . '.pdf';
                $filepath = $pdfDir . DIRECTORY_SEPARATOR . $filename;
                file_put_contents($filepath, $pdfBytes);
                return '/data/archives/' . $filename;
            }
        }
        return null;
    }

    /**
     * Fetches JSON array from URL.
     *
     * @param string $url
     * @return array<string, mixed>|null
     */
    protected function fetchJsonUrl(string $url): ?array
    {
        if (isset($this->callbacks['fetchJsonUrl'])) {
            return ($this->callbacks['fetchJsonUrl'])($url);
        }

        $body = $this->fetchUrl($url);
        if ($body !== false && !empty($body)) {
            $decoded = json_decode((string) $body, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * Fetches raw URL content.
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
                'timeout' => 10,
                'header' => "User-Agent: Indieinabox WebArchiver\r\n"
            ]
        ]);
        return @file_get_contents($url, false, $ctx);
    }
}
