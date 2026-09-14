<?php

declare(strict_types=1);

namespace Indieinabox\Core;

use Closure;
use Indieinabox\Core\Exceptions\ContainerException;
use Indieinabox\Core\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;

/**
 * PSR-11 compliant Dependency Injection Container with singleton binding,
 * factory resolution, and constructor autowiring.
 */
class Container implements ContainerInterface
{
    private static ?self $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * @var array<string, Closure|string>
     */
    private array $bindings = [];

    /**
     * @var array<string, bool>
     */
    private array $singletons = [];

    /**
     * Returns the singleton instance of the container.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Sets or resets the global container instance.
     */
    public static function setInstance(?self $container): void
    {
        self::$instance = $container;
    }

    /**
     * Registers an existing object instance into the container.
     */
    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Registers a binding with the container.
     */
    public function bind(string $id, Closure|string $concrete, bool $singleton = false): void
    {
        $this->bindings[$id] = $concrete;
        $this->singletons[$id] = $singleton;
    }

    /**
     * Registers a shared singleton binding with the container.
     */
    public function singleton(string $id, Closure|string $concrete): void
    {
        $this->bind($id, $concrete, true);
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     * @throws NotFoundException No entry was found for this identifier.
     * @throws ContainerException Error while retrieving the entry.
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            try {
                $concrete = $this->bindings[$id];
                $resolved = $concrete instanceof Closure ? $concrete($this) : $this->make($concrete);

                if (!empty($this->singletons[$id])) {
                    $this->instances[$id] = $resolved;
                }
                return $resolved;
            } catch (Throwable $e) {
                throw new ContainerException("Error resolving binding [{$id}]: " . $e->getMessage(), 0, $e);
            }
        }

        throw new NotFoundException("Entry not found in container: {$id}");
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     *
     * @param string $id Identifier of the entry to look for.
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]);
    }

    /**
     * Resolves a class with constructor dependency autowiring.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @param array<string, mixed> $parameters
     * @return T
     * @throws ContainerException
     */
    public function make(string $abstract, array $parameters = []): object
    {
        if (!class_exists($abstract)) {
            throw new ContainerException("Target class [{$abstract}] does not exist.");
        }

        try {
            $reflector = new ReflectionClass($abstract);
            if (!$reflector->isInstantiable()) {
                throw new ContainerException("Target class [{$abstract}] is not instantiable.");
            }

            $constructor = $reflector->getConstructor();
            if ($constructor === null) {
                /** @var T */
                return new $abstract();
            }

            $dependencies = [];
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();

                if (array_key_exists($paramName, $parameters)) {
                    $dependencies[] = $parameters[$paramName];
                    continue;
                }

                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $typeName = $type->getName();
                    if ($this->has($typeName)) {
                        $dependencies[] = $this->get($typeName);
                        continue;
                    }
                    if (class_exists($typeName)) {
                        try {
                            $dependencies[] = $this->make($typeName);
                            continue;
                        } catch (ContainerException $e) {
                            if ($param->isDefaultValueAvailable()) {
                                $dependencies[] = $param->getDefaultValue();
                                continue;
                            }
                            if ($param->allowsNull()) {
                                $dependencies[] = null;
                                continue;
                            }
                            throw $e;
                        }
                    }
                }

                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $dependencies[] = null;
                } else {
                    throw new ContainerException("Unable to resolve dependency [\${$paramName}] for class [{$abstract}].");
                }
            }

            /** @var T */
            return $reflector->newInstanceArgs($dependencies);
        } catch (ContainerException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ContainerException("Failed to construct [{$abstract}]: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Flushes all stored instances and bindings.
     */
    public function flush(): void
    {
        $this->instances = [];
        $this->bindings = [];
        $this->singletons = [];
    }
}
