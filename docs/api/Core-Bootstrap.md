# Bootstrap
**Namespace:** `Indieinabox\Core`

## Methods

### checkVersion()
`public static function checkVersion(int $versionId = 80509, string $version = '8.5.9', string $sapi = 'cli', ?callable $exitHandler = null, ?callable $headerHandler = null, ?callable $echoHandler = null, ?callable $outputHandler = null): bool`

Verifies that the current PHP version meets the minimum requirement (8.2.0).

@param int $versionId
@param string $version
@param string $sapi
@param callable|null $exitHandler
@param callable|null $headerHandler
@param callable|null $echoHandler
@param callable|null $outputHandler
@return bool Returns true if version requirement is satisfied.

### registerAutoloader()
`public static function registerAutoloader(string $baseDir): Closure`

Registers Composer and fallback PSR-4 autoloader for the Indieinabox namespace.

@param string $baseDir
@return \Closure The registered PSR-4 fallback autoloader closure.

### loadFunctions()
`public static function loadFunctions(string $baseDir): array`

Glob-loads all helper function scripts from app/functions.

@param string $baseDir
@return array<string> List of loaded function files.

### loadConfig()
`public static function loadConfig(string $baseDir, string $sapi = 'cli', ?callable $exitHandler = null, ?callable $installHandler = null, ?callable $dieHandler = null, ?callable $outputHandler = null): array`

Validates and loads application database configuration from .config.php.

@param string $baseDir
@param string $sapi
@param callable|null $exitHandler
@param callable|null $installHandler
@param callable|null $dieHandler
@param callable|null $outputHandler
@return array<string, mixed>

### connectDatabase()
`public static function connectDatabase(array $dbConfig, ?callable $dieHandler = null): void`

Initializes Database static data directory and connects SQLite database.

@param array<string, mixed> $dbConfig
@param callable|null $dieHandler
@return void

### run()
`public static function run(string $baseDir): void`

Runs the complete bootstrapping pipeline.

@param string $baseDir
@return void
