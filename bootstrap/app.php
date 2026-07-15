<?php

declare(strict_types=1);

// @phpstan-ignore-next-line
if (PHP_VERSION_ID < 80200) {
    $errorMessage = "Error: IndieInABox requires PHP version 8.2.0 or higher. "
        . "Your current PHP version is " . PHP_VERSION . ". "
        . "Please upgrade your PHP installation.";
    if (PHP_SAPI === 'cli') {
        file_put_contents('php://stderr', "\033[31;1m" . $errorMessage . "\033[0m\n");
        exit(1);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html>
<html>
<head>
    <title>PHP Version Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f7fafc;
            color: #2d3748;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .card {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            max-width: 500px;
            width: 100%;
            border-top: 4px solid #e53e3e;
        }
        h1 { color: #e53e3e; font-size: 1.5rem; margin-top: 0; }
        p { line-height: 1.6; margin-bottom: 1.5rem; }
        code {
            background-color: #edf2f7;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class='card'>
        <h1>PHP Version Error</h1>
        <p><strong>IndieInABox</strong> requires PHP version <strong>8.2.0</strong> or higher.</p>
        <p>Your current PHP version is <code>" . htmlspecialchars(PHP_VERSION)
            . "</code> which is too old. Please upgrade PHP to run this application.</p>
    </div>
</body>
</html>";
        exit(1);
    }
}

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

// 4. Check for database configuration
$configFile = dirname(__DIR__) . '/.config.php';
if (!file_exists($configFile)) {
    if (PHP_SAPI === 'cli') {
        file_put_contents('php://stderr', "\033[31;1mError: Database is not configured. Please run the web installer first.\033[0m\n");
        exit(1);
    } else {
        require_once dirname(__DIR__) . '/install.php';
        exit;
    }
}

$dbConfig = require $configFile;
if (!isset($dbConfig['data_dir'])) {
    // Graceful fallback for legacy configs
    if (isset($dbConfig['db_path'])) {
        $dbConfig['data_dir'] = dirname($dbConfig['db_path']);
    } else {
        die("Error: Invalid .config.php format. Missing 'data_dir'.");
    }
}

// 5. Connect to the SQLite Database
try {
    $dbPath = $dbConfig['data_dir'] . '/.indieinabox.sqlite';
    
    // Check if path is provided in config explicitly
    if (isset($dbConfig['db_path']) && file_exists($dbConfig['db_path'])) {
        $dbPath = $dbConfig['db_path'];
    }

    \Indieinabox\Database::$dataDir = $dbConfig['data_dir'];
    \Indieinabox\Database::connect($dbPath);
} catch (\Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}
