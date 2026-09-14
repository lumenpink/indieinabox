<?php

declare(strict_types=1);

use Indieinabox\Version;

test('Version::getBaseVersion returns semver string', function () {
    $base = Version::getBaseVersion();
    expect($base)->toBeString();
    expect($base)->toMatch('/^\d+\.\d+\.\d+/');
    expect($base)->toBe(Version::VERSION);
});

test('Version::get returns valid semver string', function () {
    $ver = Version::get();
    expect($ver)->toBeString();
    expect($ver)->toMatch('/^\d+\.\d+\.\d+/');
});

test('Version::getGitCommitHash returns 7-character hash or null', function () {
    $hash = Version::getGitCommitHash();
    if ($hash !== null) {
        expect(strlen($hash))->toBeGreaterThanOrEqual(7);
        expect($hash)->toMatch('/^[a-f0-9]+$/i');
    } else {
        expect($hash)->toBeNull();
    }
});

test('Version::isCompiled reflects whether compiled constant is defined', function () {
    $expected = defined('INDIEINABOX_COMPILED_VERSION');
    expect(Version::isCompiled())->toBe($expected);
});

test('Version::getBuildDate reflects build date constant if defined', function () {
    $expected = defined('INDIEINABOX_BUILD_DATE') ? (string) constant('INDIEINABOX_BUILD_DATE') : null;
    expect(Version::getBuildDate())->toBe($expected);
});
