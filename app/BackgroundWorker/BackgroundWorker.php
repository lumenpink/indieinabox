<?php

declare(strict_types=1);

namespace Indieinabox\BackgroundWorker;

use Indieinabox\BackgroundWorker\ArchiveProcessor;
use Indieinabox\BackgroundWorker\InboxProcessor;
use Indieinabox\BackgroundWorker\OutboxDispatcher;
use Indieinabox\BackgroundWorker\OutgoingWebmentionDispatcher;
use Indieinabox\BackgroundWorker\WebmentionDiscovery;
use Indieinabox\Core\Database;
use Indieinabox\Services\BackupService;
use Indieinabox\Services\UpdateService;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;
use Indieinabox\Twtxt\TwtxtManager;
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
        $discovery = new WebmentionDiscovery($this->site, $this->db);
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

        $backupService = new BackupService($this->site);
        $backupService->run();

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
        $result = UpdateService::processScheduledUpdate();
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
        $processor = new InboxProcessor($this->site, $this->db);
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
        $processor = new ArchiveProcessor($this->site, $this->db);
        $processor->process();
    }
}
