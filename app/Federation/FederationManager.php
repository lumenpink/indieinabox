<?php

declare(strict_types=1);

namespace Indieinabox\Federation;

use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Site\Site;
use RuntimeException;

/**
 * Registry and orchestrator for protocol adapters (ActivityPub, Twtxt, Lemmy, etc.).
 */
class FederationManager
{
    private Site $site;

    /**
     * @var array<string, FederationAdapter>
     */
    private array $adapters = [];

    public function __construct(?Site $site = null)
    {
        $this->site = $site ?? (
            \Indieinabox\Core\Container::getInstance()->has(Site::class)
                ? \Indieinabox\Core\Container::getInstance()->get(Site::class)
                : ($GLOBALS['site'] ?? new Site())
        );
        $this->registerDefaultAdapters();
    }

    private function registerDefaultAdapters(): void
    {
        $this->register(new ActivityPubAdapter($this->site));
    }

    /**
     * Registers a protocol adapter.
     */
    public function register(FederationAdapter $adapter): void
    {
        $this->adapters[strtolower($adapter->getProtocol())] = $adapter;
    }

    /**
     * Checks if an adapter is available for a protocol.
     */
    public function has(string $protocol): bool
    {
        $proto = strtolower($protocol);
        if (isset($this->adapters[$proto])) {
            return true;
        }

        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($protocol)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolves an adapter for the specified protocol.
     */
    public function get(string $protocol): FederationAdapter
    {
        $proto = strtolower($protocol);
        if (isset($this->adapters[$proto])) {
            return $this->adapters[$proto];
        }

        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($protocol)) {
                return $adapter;
            }
        }

        throw new RuntimeException("No federation adapter registered for protocol [{$protocol}].");
    }

    /**
     * Returns all registered adapters.
     *
     * @return array<string, FederationAdapter>
     */
    public function all(): array
    {
        return $this->adapters;
    }
}
