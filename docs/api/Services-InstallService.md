# InstallService
**Namespace:** `Indieinabox\Services`

Service responsible for the atomic 4-step installation workflow:
1. Configure DB location (and data directory, writing .config.php)
2. Execute DB install / migration
3. Configure site settings & seed default welcome content
4. Build the initial static site

## Properties

### `private ?Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(?Indieinabox\Site\Site $site = null)`

### install()
`public function install(array $params = []): array`

Executes the installation workflow atomically in sequence.

@param array<string, mixed> $params
@return array<string, mixed>

### configureDbLocation()
`public function configureDbLocation(string $baseDir, string $dbPath, ?string $dataDir = null): array`

Step 1: Configures database and data directory paths and writes .config.php.

@return array{0: string, 1: string} [fullDbPath, fullDataDir]

### migrateDatabase()
`public function migrateDatabase(string $fullDbPath, string $fullDataDir): void`

Step 2: Connects to SQLite and applies database schema migrations.

### configureSiteSettings()
`public function configureSiteSettings(string $baseDir, array $settings): void`

Step 3: Persists site parameters into the database and seeds default welcome content.

@param array<string, mixed> $settings

### buildSite()
`public function buildSite(string $baseDir, string $fullDataDir, string $contentDir): void`

Step 4: Executes initial static site build.
