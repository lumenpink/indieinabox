<?php

declare(strict_types=1);

namespace Indieinabox\Events;

use Indieinabox\Events\Contracts\EventDispatcherInterface;
use Indieinabox\Events\Contracts\StoppableEventInterface;

/**
 * Standard in-memory Event Dispatcher implementation.
 */
class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<class-string, array<int, array<int, callable>>>
     */
    private array $listeners = [];

    /**
     * @var array<class-string, array<int, callable>>
     */
    private array $sortedListeners = [];

    #[\Override]
    public function dispatch(object $event): object
    {
        $eventClass = get_class($event);
        $listeners = $this->resolveListenersForEvent($eventClass);

        foreach ($listeners as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }

    #[\Override]
    public function listen(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][$priority][] = $listener;
        unset($this->sortedListeners[$eventClass]);
    }

    #[\Override]
    public function hasListeners(string $eventClass): bool
    {
        return !empty($this->listeners[$eventClass]);
    }

    #[\Override]
    public function getListeners(string $eventClass): array
    {
        if (isset($this->sortedListeners[$eventClass])) {
            return $this->sortedListeners[$eventClass];
        }

        if (!isset($this->listeners[$eventClass])) {
            return [];
        }

        $priorities = $this->listeners[$eventClass];
        krsort($priorities); // Higher priority executes first

        $flattened = [];
        foreach ($priorities as $callbacks) {
            foreach ($callbacks as $callback) {
                $flattened[] = $callback;
            }
        }

        $this->sortedListeners[$eventClass] = $flattened;
        return $flattened;
    }

    #[\Override]
    public function clearListeners(?string $eventClass = null): void
    {
        if ($eventClass === null) {
            $this->listeners = [];
            $this->sortedListeners = [];
        } else {
            unset($this->listeners[$eventClass], $this->sortedListeners[$eventClass]);
        }
    }

    /**
     * Resolves all matching listeners for an event, including parent classes and interfaces.
     *
     * @param class-string $eventClass
     * @return array<int, callable>
     */
    private function resolveListenersForEvent(string $eventClass): array
    {
        $matched = [];

        // Direct match
        if ($this->hasListeners($eventClass)) {
            $matched = array_merge($matched, $this->getListeners($eventClass));
        }

        // Parent classes and interfaces
        foreach ($this->listeners as $registeredClass => $priorities) {
            if ($registeredClass !== $eventClass && is_a($eventClass, $registeredClass, true)) {
                $matched = array_merge($matched, $this->getListeners($registeredClass));
            }
        }

        return $matched;
    }
}
