<?php

declare(strict_types=1);

use Indieinabox\Events\Contracts\EventDispatcherInterface;
use Indieinabox\Events\DomainEvent;
use Indieinabox\Events\EventDispatcher;
use Indieinabox\Events\FollowActivityReceivedEvent;
use Indieinabox\Events\PostPublishedEvent;
use Indieinabox\Events\WebmentionReceivedEvent;
use Indieinabox\Services\PublishPostService;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;

describe('EventDispatcher', function () {
    it('implements EventDispatcherInterface and manages listeners', function () {
        $dispatcher = new EventDispatcher();
        expect($dispatcher)->toBeInstanceOf(EventDispatcherInterface::class);

        expect($dispatcher->hasListeners(PostPublishedEvent::class))->toBeFalse();
        expect($dispatcher->getListeners(PostPublishedEvent::class))->toBeEmpty();

        $listener = fn(PostPublishedEvent $e) => null;
        $dispatcher->listen(PostPublishedEvent::class, $listener);

        expect($dispatcher->hasListeners(PostPublishedEvent::class))->toBeTrue();
        expect($dispatcher->getListeners(PostPublishedEvent::class))->toHaveCount(1);

        $dispatcher->clearListeners(PostPublishedEvent::class);
        expect($dispatcher->hasListeners(PostPublishedEvent::class))->toBeFalse();
    });

    it('dispatches events to registered listeners and passes event data', function () {
        $dispatcher = new EventDispatcher();
        $received = [];

        $dispatcher->listen(PostPublishedEvent::class, function (PostPublishedEvent $event) use (&$received) {
            $received[] = $event->getSlug();
        });

        $event = new PostPublishedEvent(
            '/notes/2026-09-16',
            '/path/to/notes/2026-09-16.md',
            'note',
            null,
            'Hello IndieWeb!'
        );

        $returned = $dispatcher->dispatch($event);
        expect($returned)->toBe($event);
        expect($received)->toBe(['/notes/2026-09-16']);
        expect($event->eventId())->not->toBeEmpty();
        expect($event->occurredOn())->toBeInstanceOf(DateTimeImmutable::class);
    });

    it('orders listener execution by priority descending', function () {
        $dispatcher = new EventDispatcher();
        $order = [];

        $dispatcher->listen(FollowActivityReceivedEvent::class, function () use (&$order) {
            $order[] = 'low priority';
        }, -10);

        $dispatcher->listen(FollowActivityReceivedEvent::class, function () use (&$order) {
            $order[] = 'high priority';
        }, 100);

        $dispatcher->listen(FollowActivityReceivedEvent::class, function () use (&$order) {
            $order[] = 'default priority';
        }, 0);

        $event = new FollowActivityReceivedEvent(
            'https://remote.social/users/alice',
            'https://remote.social/users/alice/inbox',
            'Follow'
        );

        $dispatcher->dispatch($event);

        expect($order)->toBe(['high priority', 'default priority', 'low priority']);
    });

    it('propagates events to listeners registered for base classes or interfaces', function () {
        $dispatcher = new EventDispatcher();
        $dispatchedBase = false;

        $dispatcher->listen(DomainEvent::class, function (DomainEvent $event) use (&$dispatchedBase) {
            $dispatchedBase = true;
        });

        $webmentionEvent = new WebmentionReceivedEvent(
            'https://example.com/mention',
            'https://myblog.com/post-1',
            'like'
        );

        $dispatcher->dispatch($webmentionEvent);
        expect($dispatchedBase)->toBeTrue();
        expect($webmentionEvent->getType())->toBe('like');
    });

    it('halts listener propagation when stopPropagation is invoked', function () {
        $dispatcher = new EventDispatcher();
        $executionCount = 0;

        $dispatcher->listen(PostPublishedEvent::class, function (PostPublishedEvent $event) use (&$executionCount) {
            $executionCount++;
            $event->stopPropagation();
        }, 10);

        $dispatcher->listen(PostPublishedEvent::class, function (PostPublishedEvent $event) use (&$executionCount) {
            $executionCount++;
        }, 0);

        $event = new PostPublishedEvent(
            '/articles/test',
            '/path/to/test.md',
            'article',
            'Test Title',
            'Body text'
        );

        $dispatcher->dispatch($event);
        expect($executionCount)->toBe(1);
        expect($event->isPropagationStopped())->toBeTrue();
    });

    it('integrates with PublishPostService to broadcast PostPublishedEvent', function () {
        $tempDir = sys_get_temp_dir() . '/publish_event_test_' . uniqid('', true);
        mkdir($tempDir . '/content/notes', 0755, true);

        \Indieinabox\Core\Database::$dataDir = $tempDir . '/data';
        mkdir($tempDir . '/data', 0755, true);

        $paths = new Paths(
            $tempDir,
            $tempDir . '/public_html',
            $tempDir . '/public_gemini',
            $tempDir . '/public_gopher',
            $tempDir . '/public_media',
            'content',
            'resources'
        );
        $site = new Site(null, $paths);

        $dispatcher = new EventDispatcher();
        $publishedSlug = null;

        $dispatcher->listen(PostPublishedEvent::class, function (PostPublishedEvent $event) use (&$publishedSlug) {
            $publishedSlug = $event->getSlug();
        });

        $service = new PublishPostService($site, null, $dispatcher);
        $result = $service->publish('Short note with event broadcast!');

        expect($publishedSlug)->not->toBeNull();
        expect($publishedSlug)->toBe($result['slug']);

        // Clean up
        exec('rm -rf ' . escapeshellarg($tempDir));
    });
});
