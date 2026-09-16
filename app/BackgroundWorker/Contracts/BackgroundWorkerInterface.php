<?php

declare(strict_types=1);

namespace Indieinabox\BackgroundWorker\Contracts;

/**
 * Interface BackgroundWorkerInterface
 *
 * Coordinates and dispatches background processing pipelines:
 * inbox queue, outbox delivery, outgoing webmentions, archive snapshots,
 * backups, and application updates.
 */
interface BackgroundWorkerInterface
{
    /**
     * Executes all background tasks sequentially under cron lock.
     */
    public function runAll(): void;

    /**
     * Processes incoming queue (Webmentions, ActivityPub activities, site rebuilds).
     */
    public function processInboxQueue(): void;

    /**
     * Processes outgoing queue (ActivityPub outbox delivery).
     */
    public function processOutbox(): void;

    /**
     * Processes outgoing webmentions delivery.
     */
    public function processOutgoingWebmentions(): void;

    /**
     * Processes archive queue (Wayback Machine and snapshots).
     */
    public function processArchiveQueue(): void;

    /**
     * Fetches remote Twtxt timeline and hub mentions asynchronously.
     */
    public function processTwtxtFeeds(): void;

    /**
     * Executes scheduled daily backups if enabled.
     */
    public function processBackups(): void;

    /**
     * Discovers Webmention endpoints for queued domains.
     */
    public function processWebmentionDiscovery(): void;

    /**
     * Checks for application updates and performs auto-upgrade if enabled.
     */
    public function processUpdates(): void;
}
