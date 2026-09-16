<?php

declare(strict_types=1);

namespace Indieinabox\Events\Contracts;

/**
 * Interface EventDispatcherInterface
 *
 * Defines the contract for registering event listeners and dispatching domain events.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatches an event to all registered listeners.
     *
     * @template T of object
     * @param T $event
     * @return T
     */
    public function dispatch(object $event): object;

    /**
     * Registers a listener callback for a specific event class name.
     *
     * @param class-string $eventClass
     * @param callable $listener
     * @param int $priority Higher number executes earlier (default: 0)
     */
    public function listen(string $eventClass, callable $listener, int $priority = 0): void;

    /**
     * Checks if any listeners are registered for an event class.
     *
     * @param class-string $eventClass
     */
    public function hasListeners(string $eventClass): bool;

    /**
     * Returns all registered listeners for an event class.
     *
     * @param class-string $eventClass
     * @return array<int, callable>
     */
    public function getListeners(string $eventClass): array;

    /**
     * Clears registered listeners for a specific event class or all events.
     *
     * @param class-string|null $eventClass
     */
    public function clearListeners(?string $eventClass = null): void;
}
