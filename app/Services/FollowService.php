<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Container;
use Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface;
use Indieinabox\Repositories\SqliteActivityPubRepository;
use PDO;

/**
 * Service managing federated follower relationships and inbox distribution targets.
 */
class FollowService
{
    private ActivityPubRepositoryInterface $repository;

    public function __construct(ActivityPubRepositoryInterface|PDO|null $repository = null)
    {
        if ($repository instanceof ActivityPubRepositoryInterface) {
            $this->repository = $repository;
        } elseif ($repository instanceof PDO) {
            $this->repository = new SqliteActivityPubRepository($repository);
        } else {
            $container = class_exists(Container::class) ? Container::getInstance() : null;
            $this->repository = $container && $container->has(ActivityPubRepositoryInterface::class)
                ? $container->get(ActivityPubRepositoryInterface::class)
                : new SqliteActivityPubRepository();
        }
    }

    /**
     * Records or updates an active remote follower.
     */
    public function addFollower(string $actorUrl, string $inboxUrl, ?string $sharedInboxUrl = null): bool
    {
        return $this->repository->addFollower($actorUrl, $inboxUrl, $sharedInboxUrl);
    }

    /**
     * Removes a follower upon receiving an Unfollow activity.
     */
    public function removeFollower(string $actorUrl): bool
    {
        return $this->repository->removeFollower($actorUrl);
    }

    /**
     * Checks if the given remote actor is a current follower.
     */
    public function isFollower(string $actorUrl): bool
    {
        return $this->repository->isFollower($actorUrl);
    }

    /**
     * Retrieves all follower records.
     *
     * @return array<int, array{actor_url: string, inbox_url: string, shared_inbox_url: ?string}>
     */
    public function getFollowers(): array
    {
        return $this->repository->getFollowers();
    }

    /**
     * Returns deduplicated inbox endpoints (prioritizing sharedInboxes) for fan-out broadcasting.
     *
     * @return array<int, string>
     */
    public function getDistinctInboxes(): array
    {
        return $this->repository->getDistinctInboxes();
    }
}
