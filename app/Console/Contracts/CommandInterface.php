<?php

declare(strict_types=1);

namespace Indieinabox\Console\Contracts;

/**
 * Interface defining a standardized CLI command.
 */
interface CommandInterface
{
    /**
     * Returns the primary name of the command.
     */
    public function getName(): string;

    /**
     * Returns alternative aliases that can trigger this command.
     *
     * @return array<int, string>
     */
    public function getAliases(): array;

    /**
     * Returns a concise description of the command purpose.
     */
    public function getDescription(): string;

    /**
     * Returns usage syntax and help instructions.
     */
    public function getUsage(): string;

    /**
     * Executes the command using the given CLI arguments.
     *
     * @param array<int, string> $argv
     * @return int Exit code (0 for success, non-zero for error)
     */
    public function execute(array $argv): int;
}
