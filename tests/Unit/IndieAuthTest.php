<?php

declare(strict_types=1);

use Indieinabox\Site;
use Indieinabox\IndieAuthHandler;

$unitTempDir = __DIR__ . '/tmp_unit_indieauth';

beforeEach(function () use ($unitTempDir) {
    if (!is_dir($unitTempDir)) {
        mkdir($unitTempDir, 0777, true);
    }
});

afterEach(function () use ($unitTempDir) {
    if (is_dir($unitTempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($unitTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($unitTempDir);
    }
});

it('generates dynamic oauth-authorization-server metadata correctly', function () {
    $site = new Site();
    $site->metadata->fqdn = 'https://mycoolsite.com';

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/.well-known/oauth-authorization-server';

    $handler = new IndieAuthHandler($site);

    ob_start();
    $handler->handle();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json)->toBeArray()
        ->and($json['issuer'])->toBe('https://mycoolsite.com/')
        ->and($json['authorization_endpoint'])->toBe('https://mycoolsite.com/auth')
        ->and($json['token_endpoint'])->toBe('https://mycoolsite.com/token')
        ->and($json['code_challenge_methods_supported'])->toContain('S256');
});
