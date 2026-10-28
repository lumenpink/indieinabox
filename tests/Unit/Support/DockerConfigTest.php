<?php

declare(strict_types=1);

it('verifies that dockerfile installs gmp in builder stage', function () {
    $dockerfile = file_get_contents(dirname(__DIR__, 3) . '/docker/Dockerfile');
    expect($dockerfile)->toBeString();

    // Check that builder stage installs gmp
    $builderSection = strstr($dockerfile, 'FROM dunglas/frankenphp', true);
    expect($builderSection)->toBeString()
        ->and($builderSection)->toContain('gmp');
});

it('verifies that composer.lock specifies ext-gmp requirement', function () {
    $lockContent = file_get_contents(dirname(__DIR__, 3) . '/composer.lock');
    $lockData = json_decode((string)$lockContent, true);
    expect($lockData)->toBeArray();
    expect($lockData['platform'])->toHaveKey('ext-gmp');
});
