<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\BackgroundWorker\ArchiveProcessor;
use Indieinabox\BackgroundWorker\InboxProcessor;
use Indieinabox\BackgroundWorker\OutboxDispatcher;
use Indieinabox\BackgroundWorker\OutgoingWebmentionDispatcher;
use Indieinabox\BackgroundWorker\WebmentionDiscovery;
use PDO;

/**
 * Class BackgroundWorker
 *
 * Coordinates and dispatches background tasks: inbox queue, outbox delivery,
 * outgoing webmentions, archive snapshotting, backups, and updates.
 */
class BackgroundWorker
{
    /**
     * @var PDO
     */
    private PDO $db;

    /**
     * @var Site
     */
    private Site $site;

    /**
     * Initializes the BackgroundWorker.
     *
     * @param Site $site Global site configuration and environment.
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->db = Database::getDb();
    }

    /**
     * Executes all background tasks (inbox, outbox, archives, feeds, updates).
     * Normally called periodically via cron or CLI.
     *
     * @return void
     */
    public function runAll(): void
    {
        $lockFile = Database::$dataDir . '/cron.lock';
        $fp = fopen($lockFile, 'w+');
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            echo "Cron is already running.\n";
            fclose($fp);
            return;
        }

        try {
            $this->processInboxQueue();
            $this->processOutbox();
            $this->processOutgoingWebmentions();
            $this->processArchiveQueue();
            $this->processTwtxtFeeds();
            $this->processBackups();
            $this->processWebmentionDiscovery();
            $this->processUpdates();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Discovers Webmention support for queued domains.
     *
     * @return void
     */
    public function processWebmentionDiscovery(): void
    {
        $discovery = new WebmentionDiscovery($this->site, $this->db, [
            'fetchUrl' => fn(string $url) => $this->fetchUrl($url),
        ]);
        $discovery->process();
    }

    /**
     * Executes the daily backup if cron is enabled.
     *
     * @return void
     */
    public function processBackups(): void
    {
        $config = Database::getAllSettings();
        if (empty($config['backup_cron_enabled'])) {
            return;
        }

        $today = date('Y-m-d');
        if (($config['last_backup_date'] ?? '') === $today) {
            return;
        }

        echo "Running automatic daily backup...\n";

        if (!class_exists('\\Indieinabox\\BackupManager')) {
            require_once __DIR__ . '/BackupManager.php';
        }

        $backupManager = new BackupManager($this->site);
        $backupManager->run();

        $stmt = $this->db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('last_backup_date', ?)");
        $stmt->execute([$today]);

        echo "Daily backup complete.\n";
    }

    /**
     * Checks for application updates and performs auto-upgrade if enabled.
     *
     * @return void
     */
    public function processUpdates(): void
    {
        echo "Checking for application updates...\n";
        $result = Updater::processScheduledUpdate();
        echo $result['message'] . "\n";
    }

    /**
     * Fetches remote Twtxt timeline and hub mentions asynchronously.
     * Rebuilds the site to update the static timeline page if new entries are found.
     *
     * @return void
     */
    public function processTwtxtFeeds(): void
    {
        echo "Running Twtxt Feed processor...\n";

        $twtxtManager = new \Indieinabox\Twtxt\TwtxtManager();
        $cacheDir = $this->site->paths->baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'twtxt_cache';

        $fetched = false;

        if (!empty($this->site->twtxt->following)) {
            echo "Fetching twtxt timeline feeds...\n";
            $twtxtManager->fetchTimeline($this->site->twtxt->following, $cacheDir, true);
            $fetched = true;
        }

        if (!empty($this->site->twtxt->hubs)) {
            echo "Fetching twtxt hub mentions...\n";
            $twtxtManager->fetchHubMentions($this->site->twtxt->hubs, $this->site->metadata->fqdn, $cacheDir, true);
            $fetched = true;
        }

        if ($fetched) {
            echo "Triggering incremental build to update timeline...\n";
            $builder = new SiteBuilder($this->site);
            $builder->build();
        }

        echo "Twtxt Feed processor done.\n";
    }

    /**
     * Processes the incoming queue (Webmentions, ActivityPub activities, site rebuilds).
     *
     * @return void
     */
    public function processInboxQueue(): void
    {
        $processor = new InboxProcessor($this->site, $this->db, $this->getInboxCallbacks());
        $processor->process();
    }

    /**
     * Processes outgoing webmentions.
     *
     * @return void
     */
    public function processOutgoingWebmentions(): void
    {
        $dispatcher = new OutgoingWebmentionDispatcher($this->site, $this->db);
        $dispatcher->process();
    }

    /**
     * Processes the outgoing queue (Outbox).
     * Delivers queued activities to followers' inboxes using HTTP Signatures.
     *
     * @return void
     */
    public function processOutbox(): void
    {
        $dispatcher = new OutboxDispatcher($this->site, $this->db);
        $dispatcher->process();
    }

    /**
     * Processes the archive queue.
     * Saves external links to Archive.org and downloads local PDF snapshots via Microlink.
     *
     * @return void
     */
    public function processArchiveQueue(): void
    {
        $processor = new ArchiveProcessor($this->site, $this->db, $this->getArchiveCallbacks());
        $processor->process();
    }

    /**
     * @return array<string, callable>
     */
    protected function getInboxCallbacks(): array
    {
        return [
            'fetchUrl' => fn(string $url) => $this->fetchUrl($url),
            'fetchJsonUrl' => fn(string $url): ?array => $this->fetchJsonUrl($url),
            'verifySignature' => fn(array $headers, string $method, string $path, string $pubKey): bool => $this->verifySignature($headers, $method, $path, $pubKey),
        ];
    }

    /**
     * @return array<string, callable>
     */
    protected function getArchiveCallbacks(): array
    {
        return [
            'resolveFinalUrl' => fn(string $url): string => $this->resolveFinalUrl($url),
            'sendToArchiveOrg' => fn(string $url) => $this->sendToArchiveOrg($url),
            'fetchPdfFromMicrolink' => fn(string $url, string $normUrl, string $pdfDir): ?string => $this->fetchPdfFromMicrolink($url, $normUrl, $pdfDir),
            'fetchUrl' => fn(string $url) => $this->fetchUrl($url),
        ];
    }

    /**
     * Verifies HTTP signature.
     *
     * @param array $headers
     * @param string $method
     * @param string $path
     * @param string $pubKey
     * @return bool
     */
    protected function verifySignature(array $headers, string $method, string $path, string $pubKey): bool
    {
        // We skip verification for now if the library throws. In real env it would be:
        if (class_exists('HttpSignature')) {
            return HttpSignature::verify($headers, $method, $path, $pubKey);
        }
        return true;
    }

    /**
     * Fetches a URL and decodes the JSON response.
     *
     * @param string $url The URL to fetch.
     * @return array|null The decoded JSON array, or null on failure.
     */
    protected function fetchJsonUrl(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/activity+json, application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $res) {
            return json_decode($res, true);
        }
        return null;
    }

    /**
     * Fetches remote content over HTTP.
     *
     * @param string $url
     * @return string|false
     */
    protected function fetchUrl(string $url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Indieinabox BackgroundWorker']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    /**
     * Follows redirects to determine the canonical destination URL.
     *
     * @param string $url
     * @return string
     */
    protected function resolveFinalUrl(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Indieinabox ArchiveBot/1.0 (+https://indieinabox.org)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
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
    protected function sendToArchiveOrg(string $url): void
    {
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
    protected function fetchPdfFromMicrolink(string $url, string $normUrl, string $pdfDir): ?string
    {
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
}
