<?php

declare(strict_types=1);

namespace Indieinabox\Core;

class Bootstrap
{
    /**
     * Verifies that the current PHP version meets the minimum requirement (8.2.0).
     *
     * @param int $versionId
     * @param string $version
     * @param string $sapi
     * @param callable|null $exitHandler
     * @param callable|null $headerHandler
     * @param callable|null $echoHandler
     * @param callable|null $outputHandler
     * @return bool Returns true if version requirement is satisfied.
     */
    public static function checkVersion(
        int $versionId = PHP_VERSION_ID,
        string $version = PHP_VERSION,
        string $sapi = PHP_SAPI,
        ?callable $exitHandler = null,
        ?callable $headerHandler = null,
        ?callable $echoHandler = null,
        ?callable $outputHandler = null
    ): bool {
        if ($versionId >= 80200) {
            return true;
        }

        $errorMessage = "Error: IndieInABox requires PHP version 8.2.0 or higher. "
            . "Your current PHP version is " . $version . ". "
            . "Please upgrade your PHP installation.";

        if ($sapi === 'cli') {
            if ($outputHandler !== null) {
                $outputHandler($errorMessage);
            } else {
                // @codeCoverageIgnoreStart
                file_put_contents('php://stderr', "\033[31;1m" . $errorMessage . "\033[0m\n");
                // @codeCoverageIgnoreEnd
            }
            if ($exitHandler !== null) {
                $exitHandler(1);
                return false;
            }
            // @codeCoverageIgnoreStart
            exit(1);
            // @codeCoverageIgnoreEnd
        } else {
            if ($headerHandler !== null) {
                $headerHandler('HTTP/1.1 500 Internal Server Error');
                $headerHandler('Content-Type: text/html; charset=utf-8');
            } else {
                // @codeCoverageIgnoreStart
                header('HTTP/1.1 500 Internal Server Error');
                header('Content-Type: text/html; charset=utf-8');
                // @codeCoverageIgnoreEnd
            }

            $html = "<!DOCTYPE html>
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
        <p>Your current PHP version is <code>" . htmlspecialchars($version)
                . "</code> which is too old. Please upgrade PHP to run this application.</p>
    </div>
</body>
</html>";
            if ($echoHandler !== null) {
                $echoHandler($html);
            } else {
                // @codeCoverageIgnoreStart
                echo $html;
                // @codeCoverageIgnoreEnd
            }

            if ($exitHandler !== null) {
                $exitHandler(1);
                return false;
            }
            // @codeCoverageIgnoreStart
            exit(1);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Registers Composer and fallback PSR-4 autoloader for the Indieinabox namespace.
     *
     * @param string $baseDir
     * @return \Closure The registered PSR-4 fallback autoloader closure.
     */
    public static function registerAutoloader(string $baseDir): \Closure
    {
        $composerAutoload = $baseDir . '/vendor/autoload.php';
        if (file_exists($composerAutoload)) {
            include_once $composerAutoload;
        }

        $autoloader = function (string $completeNamespace) use ($baseDir): void {
            if (str_starts_with($completeNamespace, 'Indieinabox\\')) {
                if (class_exists($completeNamespace, false)
                    || interface_exists($completeNamespace, false)
                    || trait_exists($completeNamespace, false)
                ) {
                    return;
                }
                $relativeClass = substr($completeNamespace, 12);
                $file = $baseDir . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
                if (file_exists($file)) {
                    include_once $file;
                }
            }
        };

        spl_autoload_register($autoloader);

        return $autoloader;
    }

    /**
     * Glob-loads all helper function scripts from app/functions.
     *
     * @param string $baseDir
     * @return array<string> List of loaded function files.
     */
    public static function loadFunctions(string $baseDir): array
    {
        $loaded = [];
        $files = glob($baseDir . '/app/functions/*.php');
        if (is_array($files)) {
            foreach ($files as $filename) {
                // @codeCoverageIgnoreStart
                if (!function_exists('t')) {
                    include_once $filename;
                }
                // @codeCoverageIgnoreEnd
                $loaded[] = $filename;
            }
        }
        return $loaded;
    }

    /**
     * Validates and loads application database configuration from .config.php.
     *
     * @param string $baseDir
     * @param string $sapi
     * @param callable|null $exitHandler
     * @param callable|null $installHandler
     * @param callable|null $dieHandler
     * @param callable|null $outputHandler
     * @return array<string, mixed>
     */
    public static function loadConfig(
        string $baseDir,
        string $sapi = PHP_SAPI,
        ?callable $exitHandler = null,
        ?callable $installHandler = null,
        ?callable $dieHandler = null,
        ?callable $outputHandler = null
    ): array {
        $configFile = $baseDir . '/.config.php';

        if (!file_exists($configFile)) {
            if ($sapi === 'cli') {
                $errorMsg = "Error: Database is not configured. Please run the web installer first.";
                if ($outputHandler !== null) {
                    $outputHandler($errorMsg);
                } else {
                    // @codeCoverageIgnoreStart
                    file_put_contents(
                        'php://stderr',
                        "\033[31;1m" . $errorMsg . "\033[0m\n"
                    );
                    // @codeCoverageIgnoreEnd
                }
                if ($exitHandler !== null) {
                    $exitHandler(1);
                    return [];
                }
                // @codeCoverageIgnoreStart
                exit(1);
                // @codeCoverageIgnoreEnd
            } else {
                $installer = $baseDir . '/install.php';
                if ($installHandler !== null) {
                    $installHandler($installer);
                } else {
                    // @codeCoverageIgnoreStart
                    require_once $installer;
                    // @codeCoverageIgnoreEnd
                }
                if ($exitHandler !== null) {
                    $exitHandler(0);
                    return [];
                }
                // @codeCoverageIgnoreStart
                exit(0);
                // @codeCoverageIgnoreEnd
            }
        }

        $dbConfig = require $configFile;
        if (!is_array($dbConfig) || !isset($dbConfig['data_dir'])) {
            if (is_array($dbConfig) && isset($dbConfig['db_path'])) {
                $dbConfig['data_dir'] = dirname((string)$dbConfig['db_path']);
            } else {
                if ($dieHandler !== null) {
                    $dieHandler("Error: Invalid .config.php format. Missing 'data_dir'.");
                    return [];
                }
                // @codeCoverageIgnoreStart
                die("Error: Invalid .config.php format. Missing 'data_dir'.");
                // @codeCoverageIgnoreEnd
            }
        }

        return $dbConfig;
    }

    /**
     * Initializes Database static data directory and connects SQLite database.
     *
     * @param array<string, mixed> $dbConfig
     * @param callable|null $dieHandler
     * @return void
     */
    public static function connectDatabase(array $dbConfig, ?callable $dieHandler = null): void
    {
        try {
            $dataDir = (string)($dbConfig['data_dir'] ?? '');
            $dbPath = $dataDir . '/.indieinabox.sqlite';

            if (isset($dbConfig['db_path']) && file_exists((string)$dbConfig['db_path'])) {
                $dbPath = (string)$dbConfig['db_path'];
            }

            Database::$dataDir = $dataDir;
            Database::connect($dbPath);
        } catch (\Throwable $e) {
            if ($dieHandler !== null) {
                $dieHandler("Database Connection Error: " . $e->getMessage());
                return;
            }
            // @codeCoverageIgnoreStart
            die("Database Connection Error: " . $e->getMessage());
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Runs the complete bootstrapping pipeline.
     *
     * @param string $baseDir
     * @return void
     */
    public static function run(string $baseDir): void
    {
        self::checkVersion();
        self::registerAutoloader($baseDir);
        self::loadFunctions($baseDir);
        $dbConfig = self::loadConfig($baseDir);
        self::connectDatabase($dbConfig);
    }
}
