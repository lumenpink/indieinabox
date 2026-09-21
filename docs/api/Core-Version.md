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

### extractVersionFromRelease()
`public static function extractVersionFromRelease(array $release): ?string`

Extracts a SemVer version string from release metadata (tag name, release name, or description body).

@param array<string, mixed> $release
@return string|null

### isNewerVersion()
`public static function isNewerVersion(string $remoteVersion, ?string $currentVersion = null, ?string $remoteDate = null, ?string $currentDate = null): bool`

Determines whether a remote version/release is strictly newer than the current version/release.

@param string $remoteVersion Remote SemVer version or release tag.
@param string|null $currentVersion Current SemVer version (defaults to Version::get()).
@param string|null $remoteDate Remote release published date in ISO 8601 format.
@param string|null $currentDate Current build/commit date in ISO 8601 format (defaults to Version::getBuildDate()).
@return bool
