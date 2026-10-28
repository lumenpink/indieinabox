<?php

declare(strict_types=1);

it('verifies that ci workflow does not reference codeberg and uses github.api_url', function () {
    $ciYaml = file_get_contents(dirname(__DIR__, 3) . '/.forgejo/workflows/ci.yml');
    expect($ciYaml)->toBeString();

    expect($ciYaml)->not->toContain('codeberg.org');
    expect($ciYaml)->toContain('github.api_url');
});

it('verifies that nightly-release deletes existing assets via api', function () {
    $ciYaml = file_get_contents(dirname(__DIR__, 3) . '/.forgejo/workflows/ci.yml');
    expect($ciYaml)->toBeString();

    $nightlySection = strstr($ciYaml, 'nightly-release:', false);
    expect($nightlySection)->toBeString()
        ->and($nightlySection)->toContain('/assets/');
});
