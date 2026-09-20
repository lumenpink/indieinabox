<?php

declare(strict_types=1);

namespace Indieinabox\Events;

use DateTimeImmutable;
use Indieinabox\Events\Contracts\StoppableEventInterface;

/**
 * Base abstract class for all domain events.
 */
abstract class DomainEvent implements StoppableEventInterface
{
    private DateTimeImmutable $occurredOn;
    private string $eventId;
    private bool $propagationStopped = false;

    public function __construct()
    {
        $this->occurredOn = new DateTimeImmutable();
        $this->eventId = bin2hex(random_bytes(16));
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    #[\Override]
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    #[\Override]
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
