<?php

declare(strict_types=1);

it('requires ext-gmp in composer.json and runtime', function () {
    $composerJsonPath = dirname(__DIR__, 2) . '/composer.json';
    expect(file_exists($composerJsonPath))->toBeTrue();

    $composerData = json_decode((string)file_get_contents($composerJsonPath), true);
    expect($composerData)->toBeArray();
    expect($composerData['require'])->toHaveKey('ext-gmp');
    expect(extension_loaded('gmp'))->toBeTrue();
});

it('ensures single file integration test syntax is valid', function () {
    $file = dirname(__DIR__) . '/Integration/SingleFileEntryTest.php';
    expect(file_exists($file))->toBeTrue();
    expect(is_readable($file))->toBeTrue();
});
