<?php

declare(strict_types=1);

// Remove accents from a string
// From https://github.com/Behat/Transliterator/blob/master/src/Transliterator.php

function unaccent(string $string): string
{
    if (!preg_match('/[\x80-\xff]/', $string)) {
        return $string;
    }

    static $chars = null;
    if ($chars === null) {
        $paths = [
            __DIR__ . '/data/chars.php',
            dirname(__DIR__, 2) . '/data/chars.php',
            dirname(__DIR__) . '/data/chars.php',
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $chars = require $path;
                break;
            }
        }
        if ($chars === null) {
            throw new RuntimeException("chars.php not found");
        }
    }

    return strtr($string, $chars);
}
