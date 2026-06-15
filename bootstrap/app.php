<?php

declare(strict_types=1);

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

// 1. Include Composer's autoloader if it exists
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    include_once $composerAutoload;
}

// 2. Custom PSR-4 fallback autoloader for Indieinabox namespace mapping to app/ directory
spl_autoload_register(function ($completeNamespace) {
    if (strpos($completeNamespace, 'Indieinabox\\') === 0) {
        $relativeClass = substr($completeNamespace, 12);
        $file = dirname(__DIR__) . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        if (file_exists($file)) {
            include_once $file;
        }
    }
});

// 3. Glob-load all helper functions
foreach (glob(dirname(__DIR__) . '/app/functions/*.php') as $filename) {
    include_once $filename;
}

// 4. Glob-load data files (e.g. chars, copyright, translations)
foreach (glob(dirname(__DIR__) . '/data/*.php') as $filename) {
    include_once $filename;
}
