# Version
**Namespace:** `Indieinabox\Core`

Class Version

Provides application version information, git commit metadata, and build timestamps.

## Methods

### get()
`public static function get(): string`

Gets the full version string including build metadata.

@return string

### getGitCommitHash()
`public static function getGitCommitHash(): ?string`

Retrieves the short git commit hash if running within a git repository.

@return string|null

### getBaseVersion()
`public static function getBaseVersion(): string`

Gets the base semantic version string.

@return string

### isCompiled()
`public static function isCompiled(): bool`

Checks if the application is currently running as a single compiled executable.

@return bool

### getBuildDate()
`public static function getBuildDate(): ?string`

Gets the build timestamp if available.

@return string|null
