<?php

declare(strict_types=1);

use Indieinabox\Core\Container;
use Indieinabox\Core\Exceptions\ContainerException;
use Indieinabox\Core\Exceptions\NotFoundException;
use Indieinabox\Site;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->container = new Container();
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    $this->container->flush();
});

test('it registers and retrieves instance bindings', function () {
    /** @var \Tests\TestCase $this */
    $site = new Site();
    $site->metadata->sitename = 'Container Site';

    $this->container->instance(Site::class, $site);

    expect($this->container->has(Site::class))->toBeTrue();
    expect($this->container->get(Site::class))->toBe($site);
    expect($this->container->get(Site::class)->metadata->sitename)->toBe('Container Site');
});

test('it resolves factory closures and caches singletons', function () {
    /** @var \Tests\TestCase $this */
    $callCount = 0;
    $this->container->singleton('counter_service', function () use (&$callCount) {
        $callCount++;
        return (object)['count' => $callCount];
    });

    $s1 = $this->container->get('counter_service');
    $s2 = $this->container->get('counter_service');

    expect($s1)->toBe($s2);
    expect($callCount)->toBe(1);
});

test('it autowires constructor dependencies via reflection', function () {
    /** @var \Tests\TestCase $this */
    $site = new Site();
    $this->container->instance(Site::class, $site);

    $resolved = $this->container->make(\Indieinabox\Console\Commands\BuildCommand::class);
    expect($resolved)->toBeInstanceOf(\Indieinabox\Console\Commands\BuildCommand::class);
    expect($resolved->getName())->toBe('build');
});

test('it throws NotFoundException for unregistered identifiers', function () {
    /** @var \Tests\TestCase $this */
    $this->container->get('nonexistent_id_abc');
})->throws(NotFoundException::class);

test('it throws ContainerException for non-instantiable classes', function () {
    /** @var \Tests\TestCase $this */
    $this->container->make(\Indieinabox\Console\Commands\AbstractCommand::class);
})->throws(ContainerException::class);

test('it manages static singleton instance', function () {
    /** @var \Tests\TestCase $this */
    $c1 = Container::getInstance();
    $c2 = Container::getInstance();
    expect($c1)->toBe($c2);

    $custom = new Container();
    Container::setInstance($custom);
    expect(Container::getInstance())->toBe($custom);
    Container::setInstance(null);
});
