<?php

declare(strict_types=1);

use Indieinabox\Commands\CommandBus;
use Indieinabox\Commands\Contracts\CommandBusInterface;
use Indieinabox\Commands\CreatePostCommand;
use Indieinabox\Commands\DeletePostCommand;
use Indieinabox\Commands\UpdatePostCommand;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Site\Site;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_cmdbus_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    Database::getDb()->exec($sql);

    $this->site = new Site();
    $this->site->paths->contentDir = $this->tempDir;
    $this->site->fqdn = 'https://example.com';

    $this->contentRepo = new FileSystemContentRepository(null, $this->site);
    $this->bus = new CommandBus();
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    Database::disconnect();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('CommandBus is registered in Container and implements CommandBusInterface', function () {
    $container = Container::getInstance();
    expect($container->has(CommandBusInterface::class))->toBeTrue();
    $bus = $container->get(CommandBusInterface::class);
    expect($bus)->toBeInstanceOf(CommandBus::class);
});

test('CommandBus dispatches CreatePostCommand successfully', function () {
    /** @var \Tests\TestCase $this */
    $command = new CreatePostCommand($this->site, [
        'name' => 'Command Bus Post',
        'content' => 'Hello from CQRS command bus! #cqrs',
        'mp-slug' => 'command-bus-post',
    ]);

    $result = $this->bus->dispatch($command);
    expect($result['status'])->toBe(202);
    expect($result['kind'])->toBe('article');
    expect($result['slug'])->toBe('command-bus-post');
    expect(file_exists($result['file_path']))->toBeTrue();

    $content = file_get_contents($result['file_path']);
    expect($content)->toContain('title: "Command Bus Post"')
        ->toContain('cqrs');
});

test('CommandBus dispatches UpdatePostCommand successfully', function () {
    /** @var \Tests\TestCase $this */
    // First create a post
    $createResult = $this->bus->dispatch(new CreatePostCommand($this->site, [
        'name' => 'Original Title',
        'content' => 'Original content',
        'category' => ['initial'],
        'mp-slug' => 'update-target',
    ]));

    $postUrl = $createResult['post_url'];
    $filePath = $createResult['file_path'];

    // Now update it
    $updateCommand = new UpdatePostCommand(
        $this->site,
        $postUrl,
        ['title' => 'Updated Title', 'content' => 'Updated content body'],
        ['tags' => ['appended_tag']],
        ['tags' => ['initial']]
    );

    $updateResult = $this->bus->dispatch($updateCommand);
    expect($updateResult['status'])->toBe(200);

    $updatedContent = file_get_contents($filePath);
    expect($updatedContent)->toContain('title: "Updated Title"')
        ->toContain('Updated content body')
        ->toContain('appended_tag')
        ->not->toContain('initial');
});

test('CommandBus returns 404 when UpdatePostCommand target is not found', function () {
    /** @var \Tests\TestCase $this */
    $updateCommand = new UpdatePostCommand(
        $this->site,
        'https://example.com/article/2026/09/nonexistent.html',
        ['content' => 'new']
    );

    $result = $this->bus->dispatch($updateCommand);
    expect($result['status'])->toBe(404);
    expect($result['error'])->toBe('Not Found');
});

test('CommandBus dispatches DeletePostCommand successfully', function () {
    /** @var \Tests\TestCase $this */
    $createResult = $this->bus->dispatch(new CreatePostCommand($this->site, [
        'content' => 'Post to be deleted',
        'mp-slug' => 'delete-target',
    ]));

    $postUrl = $createResult['post_url'];
    $filePath = $createResult['file_path'];
    expect(file_exists($filePath))->toBeTrue();

    $deleteResult = $this->bus->dispatch(new DeletePostCommand($this->site, $postUrl));
    expect($deleteResult['status'])->toBe(204);
    expect(file_exists($filePath))->toBeFalse();
});

test('CommandBus returns 404 when DeletePostCommand target is not found', function () {
    /** @var \Tests\TestCase $this */
    $deleteResult = $this->bus->dispatch(new DeletePostCommand($this->site, 'https://example.com/note/2026/09/nonexistent.html'));
    expect($deleteResult['status'])->toBe(404);
    expect($deleteResult['error'])->toBe('Not Found');
});

test('CommandBus supports custom command registration and throws on unknown command', function () {
    /** @var \Tests\TestCase $this */
    $customCommand = new class {
        public string $payload = 'custom';
    };

    expect($this->bus->hasHandler(get_class($customCommand)))->toBeFalse();

    $this->bus->register(get_class($customCommand), function ($cmd) {
        return 'handled: ' . $cmd->payload;
    });

    expect($this->bus->hasHandler(get_class($customCommand)))->toBeTrue();
    expect($this->bus->dispatch($customCommand))->toBe('handled: custom');

    $unregistered = new class {};
    expect(fn () => $this->bus->dispatch($unregistered))->toThrow(InvalidArgumentException::class);
});
