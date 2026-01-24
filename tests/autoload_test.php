<?php

declare(strict_types=1);

/**
 * Autoload Function
 * php version 7-8
 *
 * @category Core
 * @package  Indieinabox
 * @author   Lumen Pink <hi@lumen.pink>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @version  GIT: 0.0.3
 * @link     https://Indieinabox.no.site.yet/
 */

/**
 * Autoload function to automatically load classes
 *
 * @param string $completeNamespace Full class name with namespace
 *
 * @return void
 */
function autoloader(string $completeNamespace): void
{
    // remove the tests prefix from the namespace
    $completeNamespace = str_replace('Tests\\', '', $completeNamespace);

    // remove the indieinabox prefix from the namespace
    $completeNamespace = str_replace('Indieinabox\\', '', $completeNamespace);

    // Convert namespace separators to directory separators
    $file = str_replace('\\', DIRECTORY_SEPARATOR, $completeNamespace);

    // Define the base directory for classes
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . '_engine' .
        DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR;

    // Complete file path
    $file = $baseDir . $file . '.php';

    // Check if file exists before including it
    if (file_exists($file)) {
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative
        include_once $file; // NOSONAR
    }

    // else {
    //     throw new \Exception("File not found: {$file}"); //NOSONAR
    // }
}

// Register the autoload function
spl_autoload_register('autoloader');
