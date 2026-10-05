<?php

declare(strict_types=1);

use Indieinabox\Http\WebRouter;
use Indieinabox\Nostr\NostrService;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;
use Indieinabox\Support\FileUtils;

it('generates compliant NIP-05 DNS verification JSON payload', function () {
    $pubKeyHex = '63b057107b4f2ac7f7ddc0bb574e97f8320fd865676329ce84e7ed455c57211e';
    $relays = [
        'wss://relay.damus.io',
        'wss://nos.lol',
    ];

    $payload = NostrService::buildNip05Payload(
        pubKeyHex: $pubKeyHex,
        nip05Name: 'lumen',
        relays: $relays,
        requestedName: 'lumen'
    );

    expect($payload)->toHaveKeys(['names', 'relays'])
        ->and($payload['names'])->toHaveKey('lumen')
        ->and($payload['names']['lumen'])->toBe($pubKeyHex)
        ->and($payload['relays'])->toHaveKey($pubKeyHex)
        ->and($payload['relays'][$pubKeyHex])->toBe($relays);
});

it('matches root or wildcard name in NIP-05 payload', function () {
    $pubKeyHex = '63b057107b4f2ac7f7ddc0bb574e97f8320fd865676329ce84e7ed455c57211e';
    $relays = ['wss://relay.damus.io'];

    // When requested with "_" (root identifier for domain, e.g. @_@lumen.pink)
    $payloadRoot = NostrService::buildNip05Payload($pubKeyHex, 'lumen', $relays, '_');
    expect($payloadRoot['names'])->toHaveKey('_');

    // When requested with non-matching name, names should be empty
    $payloadUnknown = NostrService::buildNip05Payload($pubKeyHex, 'lumen', $relays, 'unknown');
    expect($payloadUnknown['names'])->toBeEmpty();
});

it('emits .well-known/nostr.json during static site building', function () {
    $tempDir = sys_get_temp_dir() . '/nip05_build_' . uniqid();
    mkdir($tempDir . '/public_html', 0777, true);

    $paths = new Paths($tempDir);
    $paths->outputDirHtml = 'public_html';
    $site = new Site(null, $paths);
    $pubKeyHex = '63b057107b4f2ac7f7ddc0bb574e97f8320fd865676329ce84e7ed455c57211e';
    $site->config['nostr_pubkey'] = $pubKeyHex;
    $site->config['nostr_nip05_name'] = 'lumen';

    $builder = new SiteBuilder($site);
    $builder->publishFederationWellKnown();

    $expectedFile = $tempDir . '/public_html/.well-known/nostr.json';
    expect(file_exists($expectedFile))->toBeTrue();

    $content = json_decode((string) file_get_contents($expectedFile), true);
    expect($content)->toHaveKeys(['names', 'relays'])
        ->and($content['names'])->toHaveKey('lumen')
        ->and($content['names']['lumen'])->toBe($pubKeyHex);

    FileUtils::recursiveRmdir($tempDir);
});

it('serves /.well-known/nostr.json via WebRouter', function () {
    $tempDir = sys_get_temp_dir() . '/nip05_router_' . uniqid();
    mkdir($tempDir . '/public_html', 0777, true);

    $paths = new Paths($tempDir);
    $paths->outputDirHtml = 'public_html';
    $site = new Site(null, $paths);
    $pubKeyHex = '63b057107b4f2ac7f7ddc0bb574e97f8320fd865676329ce84e7ed455c57211e';
    $site->config['nostr_pubkey'] = $pubKeyHex;
    $site->config['nostr_nip05_name'] = 'lumen';

    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/.well-known/nostr.json?name=lumen';
    $_GET['name'] = 'lumen';

    $router = new WebRouter($site);
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode((string) $output, true);
    expect($json)->not->toBeNull()
        ->and($json)->toHaveKeys(['names', 'relays'])
        ->and($json['names']['lumen'])->toBe($pubKeyHex);

    FileUtils::recursiveRmdir($tempDir);
});
