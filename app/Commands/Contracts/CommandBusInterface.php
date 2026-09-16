<?php

declare(strict_types=1);

namespace Indieinabox\Commands\Contracts;

/**
 * Interface CommandBusInterface
 *
 * Defines the contract for registering and dispatching CQRS commands to their respective handlers.
 */
interface CommandBusInterface
{
    /**
     * Dispatches a command to its registered handler and returns the execution result.
     *
     * @param object $command
     * @return mixed
     */
    public function dispatch(object $command): mixed;

    /**
     * Registers a handler mapping for a specific command class.
     *
     * @param class-string $commandClass
     * @param callable|class-string $handler
     */
    public function register(string $commandClass, callable|string $handler): void;

    /**
     * Checks if a command class has a registered handler.
     *
     * @param class-string $commandClass
     */
    public function hasHandler(string $commandClass): bool;
}
