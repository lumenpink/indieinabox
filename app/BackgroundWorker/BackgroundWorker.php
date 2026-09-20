<?php

declare(strict_types=1);

namespace Indieinabox\BackgroundWorker;

use Indieinabox\BackgroundWorker\ArchiveProcessor;
use Indieinabox\BackgroundWorker\Contracts\BackgroundWorkerInterface;
use Indieinabox\BackgroundWorker\InboxProcessor;
use Indieinabox\BackgroundWorker\OutboxDispatcher;
use Indieinabox\BackgroundWorker\OutgoingWebmentionDispatcher;
use Indieinabox\BackgroundWorker\WebmentionDiscovery;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Repositories\SqliteSettingsRepository;
use Indieinabox\Services\BackupService;
use Indieinabox\Services\Contracts\BackupServiceInterface;
use Indieinabox\Services\Contracts\UpdateServiceInterface;
use Indieinabox\Services\UpdateManager;
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
class BackgroundWorker implements BackgroundWorkerInterface
{
    private PDO $db;
    private Site $site;
    private SettingsRepositoryInterface $settingsRepo;
    private UpdateServiceInterface $updateService;
    private BackupServiceInterface $backupService;

    /**
     * Initializes the BackgroundWorker with dependency injection.
     */
    public function __construct(
        Site $site,
        ?SettingsRepositoryInterface $settingsRepo = null,
        ?PDO $db = null,
        ?UpdateServiceInterface $updateService = null,
        ?BackupServiceInterface $backupService = null
    ) {
        $this->site = $site;
        $this->db = $db ?? Database::getDb();

        $container = class_exists(Container::class) ? Container::getInstance() : null;

        if ($settingsRepo !== null) {
            $this->settingsRepo = $settingsRepo;
        } elseif ($container && $container->has(SettingsRepositoryInterface::class)) {
            $this->settingsRepo = $container->get(SettingsRepositoryInterface::class);
        } else {
            $this->settingsRepo = new SqliteSettingsRepository($this->db);
        }

        if ($updateService !== null) {
            $this->updateService = $updateService;
        } elseif ($container && $container->has(UpdateServiceInterface::class)) {
            $this->updateService = $container->get(UpdateServiceInterface::class);
        } else {
            $this->updateService = new UpdateManager($this->settingsRepo);
        }

        if ($backupService !== null) {
            $this->backupService = $backupService;
        } elseif ($container && $container->has(BackupServiceInterface::class)) {
            $this->backupService = $container->get(BackupServiceInterface::class);
        } else {
            $this->backupService = new BackupService($this->site);
        }
    }

    /**
     * Executes all background tasks (inbox, outbox, archives, feeds, updates).
     * Normally called periodically via cron or CLI.
     */
    #[\Override]
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
     */
    #[\Override]
    public function processWebmentionDiscovery(): void
    {
        $discovery = new WebmentionDiscovery($this->site, $this->db);
        $discovery->process();
    }

    /**
     * Executes the daily backup if cron is enabled.
     */
    #[\Override]
    public function processBackups(): void
    {
        $config = $this->settingsRepo->all();
        if (empty($config['backup_cron_enabled'])) {
            return;
        }

        $today = date('Y-m-d');
        if (($config['last_backup_date'] ?? '') === $today) {
            return;
        }

        echo "Running automatic daily backup...\n";

        $this->backupService->run();

        $this->settingsRepo->set('last_backup_date', $today);

        echo "Daily backup complete.\n";
    }

    /**
     * Checks for application updates and performs auto-upgrade if enabled.
     */
    #[\Override]
    public function processUpdates(): void
    {
        echo "Checking for application updates...\n";
        $result = $this->updateService->processScheduledUpdate();
        echo $result['message'] . "\n";
    }

    /**
     * Fetches remote Twtxt timeline and hub mentions asynchronously.
     * Rebuilds the site to update the static timeline page if new entries are found.
     */
    #[\Override]
    public function processTwtxtFeeds(): void
    {
        echo "Running Twtxt Feed processor...\n";

        $twtxtManager = new TwtxtManager();
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
     */
    #[\Override]
    public function processInboxQueue(): void
    {
        $processor = new InboxProcessor($this->site, $this->db);
        $processor->process();
    }

    /**
     * Processes outgoing webmentions.
     */
    #[\Override]
    public function processOutgoingWebmentions(): void
    {
        $dispatcher = new OutgoingWebmentionDispatcher($this->site, $this->db);
        $dispatcher->process();
    }

    /**
     * Processes the outgoing queue (Outbox).
     * Delivers queued activities to followers' inboxes using HTTP Signatures.
     */
    #[\Override]
    public function processOutbox(): void
    {
        $dispatcher = new OutboxDispatcher($this->site, $this->db);
        $dispatcher->process();
    }

    /**
     * Processes the archive queue.
     * Saves external links to Archive.org and downloads local PDF snapshots via Microlink.
     */
    #[\Override]
    public function processArchiveQueue(): void
    {
        $processor = new ArchiveProcessor($this->site, $this->db);
        $processor->process();
    }
}
