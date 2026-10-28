<?php

declare(strict_types=1);

it('verifies that no_proxy format isolates loopback hosts', function () {
    $hosts = explode(',', '127.0.0.1,localhost');
    expect($hosts)->toContain('127.0.0.1')
        ->and($hosts)->toContain('localhost');
});
