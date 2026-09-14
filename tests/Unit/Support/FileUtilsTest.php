<?php

declare(strict_types=1);

use Indieinabox\Support\FileUtils;

it('recursively sorts array keys case-insensitively', function () {
    $array = [
        'z' => 1,
        'b' => [
            'delta' => 4,
            'Alpha' => 1,
            'charlie' => 3
        ],
        'A' => 2
    ];

    FileUtils::recursiveKsort($array);

    $keys = array_keys($array);
    expect(strtolower($keys[0]))->toBe('a')
        ->and(strtolower($keys[1]))->toBe('b')
        ->and(strtolower($keys[2]))->toBe('z');

    $subKeys = array_keys($array['b']);
    expect(strtolower($subKeys[0]))->toBe('alpha')
        ->and(strtolower($subKeys[1]))->toBe('charlie')
        ->and(strtolower($subKeys[2]))->toBe('delta');
});

it('lists and deletes directories recursively', function () {
    $tempDir = __DIR__ . '/tmp_test_fileutils';
    if (!is_dir($tempDir)) {
        mkdir($tempDir . '/nested', 0777, true);
    }
    file_put_contents($tempDir . '/file1.txt', 'hello');
    file_put_contents($tempDir . '/nested/file2.txt', 'world');

    $contents = FileUtils::getDirContents($tempDir);
    expect($contents)->toHaveCount(3); // file1.txt, nested, nested/file2.txt

    expect(FileUtils::recursiveRmdir($tempDir))->toBeTrue()
        ->and(file_exists($tempDir))->toBeFalse();
});
