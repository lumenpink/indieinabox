<?php

declare(strict_types=1);

use Indieinabox\Core\Version;

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
    if ($expected !== null) {
        expect(Version::getBuildDate())->toBe($expected);
    } else {
        $date = Version::getBuildDate();
        expect($date === null || is_string($date))->toBeTrue();
    }
});

test('Version::extractVersionFromRelease extracts SemVer from tag, name, or body', function () {
    // 1. From standard semver tag
    $rel1 = ['tag_name' => 'v0.2.0', 'name' => 'Release v0.2.0', 'body' => ''];
    expect(Version::extractVersionFromRelease($rel1))->toBe('0.2.0');

    // 2. From nightly release name
    $rel2 = ['tag_name' => 'nightly', 'name' => 'Nightly Build (0.1.0-dev.442+20260920.226203cb)', 'body' => ''];
    expect(Version::extractVersionFromRelease($rel2))->toBe('0.1.0-dev.442+20260920.226203cb');

    // 3. From release body
    $rel3 = [
        'tag_name' => 'nightly',
        'name' => 'Nightly Build',
        'body' => "Automatic build\n\nVersion: 0.1.0-dev.445+20260921.abcdef12\nCommit: 1234567"
    ];
    expect(Version::extractVersionFromRelease($rel3))->toBe('0.1.0-dev.445+20260921.abcdef12');

    // 4. Fallback when no semver present
    $rel4 = ['tag_name' => 'nightly', 'name' => 'Nightly Build', 'body' => 'No version here'];
    expect(Version::extractVersionFromRelease($rel4))->toBeNull();
});

test('Version::isNewerVersion accurately compares versions and dates', function () {
    // Newer major/minor/patch
    expect(Version::isNewerVersion('0.2.0', '0.1.0'))->toBeTrue();
    expect(Version::isNewerVersion('0.1.0', '0.2.0'))->toBeFalse();

    // Stable release vs prerelease of same base
    expect(Version::isNewerVersion('0.1.0', '0.1.0-dev.442'))->toBeTrue();
    expect(Version::isNewerVersion('0.1.0-dev.442', '0.1.0'))->toBeFalse();

    // Comparing two prereleases
    expect(Version::isNewerVersion('0.1.0-dev.445', '0.1.0-dev.442'))->toBeTrue();
    expect(Version::isNewerVersion('0.1.0-dev.440', '0.1.0-dev.442'))->toBeFalse();

    // Equal semver, differing build metadata (date/hash)
    expect(Version::isNewerVersion(
        '0.1.0-dev.442+20260921.abcdef',
        '0.1.0-dev.442+20260920.123456'
    ))->toBeTrue();

    // Older remote build than newer local build (Ticket #1 core issue)
    expect(Version::isNewerVersion(
        '0.1.0-dev.440+20260914.758abab4',
        '0.1.0-dev.442+20260920.226203cb',
        '2026-09-14T10:42:44Z',
        '2026-09-20T08:48:45Z'
    ))->toBeFalse();

    // Non-semver release tag (legacy nightly) fallback to date comparison
    expect(Version::isNewerVersion(
        'nightly',
        '0.1.0-dev.442',
        '2026-09-25T10:00:00Z',
        '2026-09-20T08:00:00Z'
    ))->toBeTrue();

    expect(Version::isNewerVersion(
        'nightly',
        '0.1.0-dev.442',
        '2026-09-15T10:00:00Z',
        '2026-09-20T08:00:00Z'
    ))->toBeFalse();
});
