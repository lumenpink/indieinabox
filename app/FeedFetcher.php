<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Services\FetchFeedsService;

/**
 * FeedFetcher handles scheduling and fetching of subscribed external syndication feeds.
 */
class FeedFetcher
{
    private FetchFeedsService $service;

    public function __construct(?FetchFeedsService $service = null)
    {
        $this->service = $service ?? new FetchFeedsService();
    }

    /**
     * Iterates through all channels and subscriptions, fetching new items for each.
     */
    public function fetchAll(): void
    {
        $this->service->fetchAll();
    }
}
