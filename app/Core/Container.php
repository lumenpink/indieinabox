<?php

declare(strict_types=1);

namespace Indieinabox\Core;

use Closure;
use Indieinabox\Core\Exceptions\ContainerException;
use Indieinabox\Core\Exceptions\NotFoundException;
use Indieinabox\Federation\ActivityPubAdapter;
use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Markdown\MarkdownParser;
use Indieinabox\Markdown\ParserInterface;
use Indieinabox\Repositories\Contracts\InteractionRepositoryInterface;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Repositories\FileInteractionRepository;
use Indieinabox\Repositories\SqliteSettingsRepository;
use Indieinabox\Site\Site;
use PDO;
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

    public function __construct()
    {
        $this->registerDefaultBindings();
    }

    /**
     * Registers default framework contracts and implementations.
     */
    public function registerDefaultBindings(): void
    {
        $this->bind(PDO::class, function () {
            return Database::getDb();
        });
        $this->singleton(SettingsRepositoryInterface::class, function (self $container) {
            $pdo = null;
            if ($container->has(PDO::class)) {
                try {
                    $pdo = $container->get(PDO::class);
                } catch (\Throwable) {
                    $pdo = null;
                }
            }
            return new SqliteSettingsRepository($pdo);
        });
        $this->singleton(InteractionRepositoryInterface::class, function (self $container) {
            return new FileInteractionRepository();
        });
        $this->bind(ParserInterface::class, MarkdownParser::class);
        $this->bind(FederationAdapter::class, ActivityPubAdapter::class);
        $this->singleton(\Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface::class, function (self $container) {
            $site = $container->has(\Indieinabox\Site\Site::class) ? $container->get(\Indieinabox\Site\Site::class) : ($GLOBALS['site'] ?? null);
            $settingsRepo = $container->has(SettingsRepositoryInterface::class) ? $container->get(SettingsRepositoryInterface::class) : null;
            return new \Indieinabox\Taxonomy\TaxonomyService($site, $settingsRepo);
        });
        $this->bind(\Indieinabox\Taxonomy\TaxonomyService::class, \Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface::class);

        $this->singleton(\Indieinabox\Taxonomy\Contracts\SeoMetadataResolverInterface::class, function (self $container) {
            $site = $container->has(\Indieinabox\Site\Site::class) ? $container->get(\Indieinabox\Site\Site::class) : ($GLOBALS['site'] ?? null);
            return new \Indieinabox\Taxonomy\SeoMetadataResolver($site);
        });
        $this->bind(\Indieinabox\Taxonomy\SeoMetadataResolver::class, \Indieinabox\Taxonomy\Contracts\SeoMetadataResolverInterface::class);

        $this->singleton(\Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface::class, function (self $container) {
            $pdo = $container->has(PDO::class) ? $container->get(PDO::class) : null;
            return new \Indieinabox\Repositories\SqliteActivityPubRepository($pdo);
        });
        $this->bind(\Indieinabox\Repositories\SqliteActivityPubRepository::class, \Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface::class);

        $this->singleton(\Indieinabox\Repositories\Contracts\MicrosubRepositoryInterface::class, function (self $container) {
            $pdo = $container->has(PDO::class) ? $container->get(PDO::class) : null;
            return new \Indieinabox\Repositories\SqliteMicrosubRepository($pdo);
        });
        $this->bind(\Indieinabox\Repositories\SqliteMicrosubRepository::class, \Indieinabox\Repositories\Contracts\MicrosubRepositoryInterface::class);

        $this->singleton(\Indieinabox\Services\Contracts\UpdateServiceInterface::class, function (self $container) {
            $settingsRepo = $container->has(SettingsRepositoryInterface::class) ? $container->get(SettingsRepositoryInterface::class) : null;
            return new \Indieinabox\Services\UpdateManager($settingsRepo);
        });
        $this->bind(\Indieinabox\Services\UpdateManager::class, \Indieinabox\Services\Contracts\UpdateServiceInterface::class);

        $this->singleton(\Indieinabox\Services\Contracts\BackupServiceInterface::class, function (self $container) {
            $site = $container->has(\Indieinabox\Site\Site::class) ? $container->get(\Indieinabox\Site\Site::class) : ($GLOBALS['site'] ?? null);
            return new \Indieinabox\Services\BackupService($site);
        });
        $this->bind(\Indieinabox\Services\BackupService::class, \Indieinabox\Services\Contracts\BackupServiceInterface::class);

        $this->singleton(\Indieinabox\BackgroundWorker\Contracts\BackgroundWorkerInterface::class, function (self $container) {
            $site = $container->has(\Indieinabox\Site\Site::class) ? $container->get(\Indieinabox\Site\Site::class) : ($GLOBALS['site'] ?? null);
            if ($site === null) {
                $paths = new \Indieinabox\Site\Paths(\Indieinabox\Core\Database::$dataDir ?: sys_get_temp_dir());
                $site = new \Indieinabox\Site\Site(null, $paths);
            }
            $settingsRepo = $container->has(SettingsRepositoryInterface::class) ? $container->get(SettingsRepositoryInterface::class) : null;
            $pdo = $container->has(PDO::class) ? $container->get(PDO::class) : null;
            $updateService = $container->has(\Indieinabox\Services\Contracts\UpdateServiceInterface::class) ? $container->get(\Indieinabox\Services\Contracts\UpdateServiceInterface::class) : null;
            $backupService = $container->has(\Indieinabox\Services\Contracts\BackupServiceInterface::class) ? $container->get(\Indieinabox\Services\Contracts\BackupServiceInterface::class) : null;
            return new \Indieinabox\BackgroundWorker\BackgroundWorker($site, $settingsRepo, $pdo, $updateService, $backupService);
        });
        $this->bind(\Indieinabox\BackgroundWorker\BackgroundWorker::class, \Indieinabox\BackgroundWorker\Contracts\BackgroundWorkerInterface::class);

        $this->singleton(\Indieinabox\Events\Contracts\EventDispatcherInterface::class, function () {
            return new \Indieinabox\Events\EventDispatcher();
        });
        $this->bind(\Indieinabox\Events\EventDispatcher::class, \Indieinabox\Events\Contracts\EventDispatcherInterface::class);

        $this->singleton(\Indieinabox\Repositories\Contracts\ContentRepositoryInterface::class, function (self $container) {
            $site = $container->has(\Indieinabox\Site\Site::class) ? $container->get(\Indieinabox\Site\Site::class) : ($GLOBALS['site'] ?? null);
            return new \Indieinabox\Repositories\FileSystemContentRepository(null, $site);
        });
        $this->bind(\Indieinabox\Repositories\FileSystemContentRepository::class, \Indieinabox\Repositories\Contracts\ContentRepositoryInterface::class);

        $this->singleton(\Indieinabox\Commands\Contracts\CommandBusInterface::class, function () {
            return new \Indieinabox\Commands\CommandBus();
        });
        $this->bind(\Indieinabox\Commands\CommandBus::class, \Indieinabox\Commands\Contracts\CommandBusInterface::class);

        $this->singleton(\Indieinabox\Services\Contracts\IngestInteractionServiceInterface::class, function (self $container) {
            $repo = $container->has(InteractionRepositoryInterface::class) ? $container->get(InteractionRepositoryInterface::class) : null;
            $dispatcher = $container->has(\Indieinabox\Events\Contracts\EventDispatcherInterface::class) ? $container->get(\Indieinabox\Events\Contracts\EventDispatcherInterface::class) : null;
            return new \Indieinabox\Services\IngestInteractionService($repo, $dispatcher);
        });
        $this->bind(\Indieinabox\Services\IngestInteractionService::class, \Indieinabox\Services\Contracts\IngestInteractionServiceInterface::class);
    }

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
     * Removes an instance from the container cache.
     */
    public function forget(string $id): void
    {
        unset($this->instances[$id]);
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
    #[\Override]
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
    #[\Override]
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
        if (isset($this->bindings[$abstract])) {
            /** @var T */
            return $this->get($abstract);
        }

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
                        try {
                            $dependencies[] = $this->get($typeName);
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
     * Clears all registered instances, bindings, and resets defaults.
     */
    public function flush(): void
    {
        $this->instances = [];
        $this->bindings = [];
        $this->singletons = [];
        $this->registerDefaultBindings();
    }
}
