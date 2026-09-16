<?php

declare(strict_types=1);

use Indieinabox\Console\Commands\AbstractCommand;
use Indieinabox\Console\ConsoleKernel;
use Indieinabox\Site\Site;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->site = new Site();
    $this->kernel = new ConsoleKernel($this->site);
});

test('it registers default commands and resolves them by name and aliases', function () {
    /** @var \Tests\TestCase $this */
    expect($this->kernel->getCommand('build'))->not->toBeNull();
    expect($this->kernel->getCommand('cron'))->not->toBeNull();
    expect($this->kernel->getCommand('fetch'))->not->toBeNull();
    expect($this->kernel->getCommand('microsub:fetch'))->not->toBeNull();
    expect($this->kernel->getCommand('post'))->not->toBeNull();
    expect($this->kernel->getCommand('profile'))->not->toBeNull();
    expect($this->kernel->getCommand('config'))->not->toBeNull();
    expect($this->kernel->getCommand('setup'))->not->toBeNull();
    expect($this->kernel->getCommand('test-links'))->not->toBeNull();
    expect($this->kernel->getCommand('backup'))->not->toBeNull();
    expect($this->kernel->getCommand('test-webmention'))->not->toBeNull();
    expect($this->kernel->getCommand('version'))->not->toBeNull();
    expect($this->kernel->getCommand('-v'))->not->toBeNull();
    expect($this->kernel->getCommand('--version'))->not->toBeNull();
    expect($this->kernel->getCommand('update'))->not->toBeNull();
});

test('it prints help message when requested', function () {
    /** @var \Tests\TestCase $this */
    ob_start();
    $exitCode = $this->kernel->handle(['indieinabox.php', '--help']);
    $output = ob_get_clean();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Indieinabox CLI Console')
        ->toContain('Available Commands:')
        ->toContain('build')
        ->toContain('cron')
        ->toContain('fetch')
        ->toContain('post')
        ->toContain('version');
});

test('it returns error on unknown command', function () {
    /** @var \Tests\TestCase $this */
    ob_start();
    $exitCode = $this->kernel->handle(['indieinabox.php', 'nonexistent-command-xyz']);
    $output = ob_get_clean();

    expect($exitCode)->toBe(1);
    expect($output)->toContain("Error: Unknown command 'nonexistent-command-xyz'");
});

test('it allows registering custom commands', function () {
    /** @var \Tests\TestCase $this */
    $mockCommand = new class($this->site) extends AbstractCommand {
        public function getName(): string
        {
            return 'custom:test';
        }
        public function getDescription(): string
        {
            return 'Custom test command';
        }
        public function execute(array $argv): int
        {
            echo "Custom executed\n";
            return 0;
        }
    };

    $this->kernel->register($mockCommand);
    expect($this->kernel->getCommand('custom:test'))->toBe($mockCommand);

    ob_start();
    $exitCode = $this->kernel->handle(['indieinabox.php', 'custom:test']);
    $output = ob_get_clean();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Custom executed');
});
