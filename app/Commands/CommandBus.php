<?php

declare(strict_types=1);

namespace Indieinabox\Commands;

use Indieinabox\Commands\Contracts\CommandBusInterface;
use Indieinabox\Core\Container;
use InvalidArgumentException;
use RuntimeException;

/**
 * Default CommandBus implementation for CQRS commands.
 */
class CommandBus implements CommandBusInterface
{
    /**
     * @var array<string, callable|string>
     */
    private array $handlers = [];

    /**
     * @param array<string, callable|string> $handlers
     */
    public function __construct(array $handlers = [])
    {
        $this->handlers = $handlers;

        // Register default handlers if none provided for standard commands
        if (!isset($this->handlers[CreatePostCommand::class])) {
            $this->register(CreatePostCommand::class, Handlers\CreatePostCommandHandler::class);
        }
        if (!isset($this->handlers[UpdatePostCommand::class])) {
            $this->register(UpdatePostCommand::class, Handlers\UpdatePostCommandHandler::class);
        }
        if (!isset($this->handlers[DeletePostCommand::class])) {
            $this->register(DeletePostCommand::class, Handlers\DeletePostCommandHandler::class);
        }
    }

    /**
     * Dispatches a command to its registered handler and returns the execution result.
     *
     * @param object $command
     * @return mixed
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function dispatch(object $command): mixed
    {
        $commandClass = get_class($command);

        if (!$this->hasHandler($commandClass)) {
            throw new InvalidArgumentException("No handler registered for command [{$commandClass}].");
        }

        $handler = $this->handlers[$commandClass];

        if (is_callable($handler)) {
            return $handler($command);
        }

        if (is_string($handler) && class_exists($handler)) {
            $container = class_exists(Container::class) ? Container::getInstance() : null;
            $instance = $container && $container->has($handler)
                ? $container->get($handler)
                : new $handler();

            if (method_exists($instance, 'handle')) {
                return $instance->handle($command);
            }

            if (is_callable($instance)) {
                return $instance($command);
            }

            throw new RuntimeException("Handler [{$handler}] must have a handle() method or be invokable.");
        }

        throw new RuntimeException("Invalid handler configured for command [{$commandClass}].");
    }

    /**
     * Registers a handler mapping for a specific command class.
     *
     * @param class-string $commandClass
     * @param callable|class-string $handler
     */
    #[\Override]
    public function register(string $commandClass, callable|string $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    /**
     * Checks if a command class has a registered handler.
     *
     * @param class-string $commandClass
     */
    #[\Override]
    public function hasHandler(string $commandClass): bool
    {
        return isset($this->handlers[$commandClass]);
    }
}
