<?php

declare(strict_types=1);

namespace Indieinabox\Support;

/**
 * Class FileUtils
 *
 * Provides filesystem traversal, directory cleanup, and recursive array manipulation utilities.
 */
class FileUtils
{
    /**
     * Recursively sorts an associative array by keys.
     *
     * @param array<string, mixed> $array
     * @return void
     */
    public static function recursiveKsort(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::recursiveKsort($value);
            }
        }
        ksort($array, SORT_STRING | SORT_FLAG_CASE);
    }

    /**
     * Recursively gets directory contents.
     *
     * @param string $dir
     * @param array<int, string> $results
     * @return array<int, string>
     */
    public static function getDirContents(string $dir, array &$results = []): array
    {
        if (!is_dir($dir)) {
            return $results;
        }

        $files = scandir($dir);
        if ($files === false) {
            return $results;
        }

        foreach ($files as $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if ($path !== false) {
                if (!is_dir($path)) {
                    $results[] = $path;
                } elseif ($value !== '.' && $value !== '..') {
                    self::getDirContents($path, $results);
                    $results[] = $path;
                }
            }
        }

        return $results;
    }

    /**
     * Recursively deletes a directory and its contents.
     *
     * @param string $dir
     * @param bool $keepRootDir
     * @return bool
     * @throws \RuntimeException
     */
    public static function recursiveRmdir(string $dir, bool $keepRootDir = false): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            throw new \RuntimeException("'$dir' is not a directory");
        }

        $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;

        try {
            $items = new \DirectoryIterator($dir);

            foreach ($items as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $path = $item->getPathname();

                if ($item->isDir()) {
                    if (!self::recursiveRmdir($path)) {
                        return false;
                    }
                } else {
                    if (!unlink($path)) {
                        throw new \RuntimeException("Failed to delete file: $path");
                    }
                }
            }

            if (!$keepRootDir && !rmdir($dir)) {
                throw new \RuntimeException("Failed to remove directory: $dir");
            }

            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException("Error while removing directory: " . $e->getMessage());
        }
    }
}
