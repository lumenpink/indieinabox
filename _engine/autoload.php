<?php

/**
 * Autoload Function
 * php version 7-8
 *
 * @category Core
 * @package  IndieInABox
 * @author   Lumen Pink <hi@lumen.pink>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @version  GIT: 0.0.3
 * @link     https://IndieInABox.no.site.yet/
 */

/**
 * Autoload function to automatically load classes
 *
 * @param string $completeNamespace Full class name with namespace
 *
 * @return void
 */
function autoloader($completeNamespace)
{
    // Convert namespace separators to directory separators
    $file = str_replace('\\', DIRECTORY_SEPARATOR, $completeNamespace);

    // Define the base directory for classes
    $base_dir = __DIR__ . DIRECTORY_SEPARATOR . '_engine' .
        DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR;

    // Complete file path
    $file = $base_dir . $file . '.php';

    // Check if file exists before including it
    if (!file_exists($file)) {
        throw new \Exception("File not found: {$file}"); //NOSONAR
    }
    include_once $file;
}

// Register the autoload function
spl_autoload_register('autoloader');
