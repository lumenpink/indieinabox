<?php

declare(strict_types=1);

namespace Tests\Integration;

use Indieinabox\Core\Container;
use Indieinabox\Http\StaticFileServer;
use Indieinabox\Http\WebRouter;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Support\FileUtils;

describe('StaticFileServer Integration', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/indie_static_integration_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/public_html', 0777, true);

        $this->paths = new Paths($this->tempDir);
        $this->paths->outputDirHtml = 'public_html';
        $this->site = new Site(null, $this->paths);

        $this->container = new Container();
        $this->container->instance(Site::class, $this->site);
        $this->container->singleton(StaticFileServer::class, fn () => new StaticFileServer($this->site));
    });

    afterEach(function () {
        FileUtils::recursiveRmdir($this->tempDir);
    });

    it('autowires WebRouter and StaticFileServer via Container', function () {
        $fileServer = $this->container->make(StaticFileServer::class);
        expect($fileServer)->toBeInstanceOf(StaticFileServer::class);

        $router = $this->container->make(WebRouter::class);
        expect($router)->toBeInstanceOf(WebRouter::class)
            ->and($router->getFileServer())->toBeInstanceOf(StaticFileServer::class);
    });

    it('integrates WebRouter with custom injected StaticFileServer instance', function () {
        $customServer = new StaticFileServer($this->site);
        $router = new WebRouter($this->site, $customServer);

        expect($router->getFileServer())->toBe($customServer);
    });

    it('executes static file dispatch across real site output directory structure', function () {
        $testFile = $this->tempDir . '/public_html/feed.xml';
        file_put_contents($testFile, '<rss><channel><title>Test Feed</title></channel></rss>');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/feed.xml';

        $fileServer = new StaticFileServer($this->site);
        $router = new WebRouter($this->site, $fileServer);

        ob_start();
        $router->handleRequest();
        $content = ob_get_clean();

        expect($content)->toBe('<rss><channel><title>Test Feed</title></channel></rss>');
    });
});
