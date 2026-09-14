<?php

declare(strict_types=1);

use Indieinabox\Site;
use Indieinabox\Micropub\QueryHandler;

test('QueryHandler responds to q=config with media endpoint and syndicate-to', function () {
    $site = new Site();
    $site->fqdn = 'https://my-blog.example.com';

    $res = QueryHandler::handle($site, 'config');
    expect($res['status'])->toBe(200);
    expect($res['body'])->toHaveKey('media-endpoint', 'https://my-blog.example.com/micropub/media');
    expect($res['body'])->toHaveKey('syndicate-to');
});

test('QueryHandler responds to q=syndicate-to', function () {
    $site = new Site();
    $res = QueryHandler::handle($site, 'syndicate-to');
    expect($res['status'])->toBe(200);
    expect($res['body'])->toHaveKey('syndicate-to');
});

test('QueryHandler returns 400 on unsupported q query', function () {
    $site = new Site();
    $res = QueryHandler::handle($site, 'unsupported-query');
    expect($res['status'])->toBe(400);
    expect($res)->toHaveKey('error', 'Invalid Query');
});
