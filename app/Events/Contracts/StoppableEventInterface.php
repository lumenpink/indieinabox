<?php

declare(strict_types=1);

namespace Indieinabox\Events\Contracts;

/**
 * Interface for events whose propagation can be halted.
 */
interface StoppableEventInterface
{
    /**
     * Determines whether listener execution should stop.
     */
    public function isPropagationStopped(): bool;

    /**
     * Halts further listener propagation.
     */
    public function stopPropagation(): void;
}
