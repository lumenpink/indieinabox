<?php

declare(strict_types=1);

namespace Indieinabox\Console;

use Indieinabox\Console\Commands\BackupCommand;
use Indieinabox\Console\Commands\BuildCommand;
use Indieinabox\Console\Commands\ConfigCommand;
use Indieinabox\Console\Commands\CronCommand;
use Indieinabox\Console\Commands\FetchCommand;
use Indieinabox\Console\Commands\LinkCheckCommand;
use Indieinabox\Console\Commands\PostCommand;
use Indieinabox\Console\Commands\ProfileCommand;
use Indieinabox\Console\Commands\SetupCommand;
use Indieinabox\Console\Commands\TestWebmentionCommand;
use Indieinabox\Console\Commands\UpdateCommand;
use Indieinabox\Console\Commands\VersionCommand;
use Indieinabox\Console\Contracts\CommandInterface;
use Indieinabox\Site\Site;

/**
 * Kernel responsible for registering, routing, and executing CLI commands.
 */
class ConsoleKernel
{
    private Site $site;

    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    /**
     * @var array<string, CommandInterface>
     */
    private array $uniqueCommands = [];

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->registerDefaultCommands();
    }

    /**
     * Registers all standard CLI commands.
     */
    private function registerDefaultCommands(): void
    {
        $this->register(new BuildCommand($this->site));
        $this->register(new CronCommand($this->site));
        $this->register(new FetchCommand($this->site));
        $this->register(new PostCommand($this->site));
        $this->register(new ProfileCommand($this->site));
        $this->register(new ConfigCommand($this->site));
        $this->register(new SetupCommand($this->site));
        $this->register(new LinkCheckCommand($this->site));
        $this->register(new BackupCommand($this->site));
        $this->register(new TestWebmentionCommand($this->site));
        $this->register(new VersionCommand($this->site));
        $this->register(new UpdateCommand($this->site));
    }

    /**
     * Registers a command and its aliases in the registry.
     */
    public function register(CommandInterface $command): void
    {
        $this->uniqueCommands[$command->getName()] = $command;
        $this->commands[$command->getName()] = $command;

        foreach ($command->getAliases() as $alias) {
            $this->commands[$alias] = $command;
        }
    }

    /**
     * Finds a command by name or alias.
     */
    public function getCommand(string $name): ?CommandInterface
    {
        return $this->commands[$name] ?? null;
    }

    /**
     * Returns all unique registered commands.
     *
     * @return array<string, CommandInterface>
     */
    public function getCommands(): array
    {
        return $this->uniqueCommands;
    }

    /**
     * Dispatches the input CLI arguments to the matching command.
     *
     * @param array<int, string> $argv
     * @return int Exit code
     */
    public function handle(array $argv): int
    {
        $requested = $argv[1] ?? '';

        if (in_array($requested, ['help', '--help', '-h'], true)) {
            $this->printHelp();
            return 0;
        }

        // Default to 'build' when no command is specified or flags are passed directly
        if ($requested === '' || str_starts_with($requested, '-')) {
            $buildCommand = $this->getCommand('build');
            if ($buildCommand !== null) {
                return $buildCommand->execute($argv);
            }
            echo "Error: Build command not registered.\n";
            return 1;
        }

        $command = $this->getCommand($requested);
        if ($command !== null) {
            return $command->execute($argv);
        }

        echo "Error: Unknown command '{$requested}'. Run 'php indieinabox.php --help' for available commands.\n";
        return 1;
    }

    /**
     * Renders a human-readable list of available commands and usage instructions.
     */
    public function printHelp(): void
    {
        echo "Indieinabox CLI Console\n";
        echo "Usage: php indieinabox.php [command] [options]\n\n";
        echo "Available Commands:\n";

        foreach ($this->uniqueCommands as $name => $cmd) {
            $padded = str_pad($name, 18);
            echo "  {$padded} {$cmd->getDescription()}\n";
        }
        echo "\n";
    }
}
