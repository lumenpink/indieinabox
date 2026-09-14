# AssetPublisher
**Namespace:** `Indieinabox\SiteBuilder`

Handles publishing and lifecycle of static files, theme assets, and media.
Also responsible for garbage collection of orphaned build files.

## Properties

### `private Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### publishViewAssets()
`public function publishViewAssets(string $dir): void`

Copies global assets from the theme's views directory to the public HTML output directory.

@param string $dir The source directory containing theme assets.

### publishStaticFiles()
`public function publishStaticFiles(string $dir): bool`

Copies general static files from the given directory to the output HTML directory.

@param string $dir The source directory containing static files.
@return bool True if copy was successful or theme was available, false otherwise.

### publishMedia()
`public function publishMedia(): void`

Copies static media files from the content directory and microsub data directory
to the public media output directory.

### copyStaticFiles()
`public function copyStaticFiles(string $dir, string $base, string $outputDir): void`

Copies static files. If the directory exists on disk, it uses file system copy.
Otherwise, it writes the embedded static files to the destination.

@param string $dir The source directory.
@param string $base The base project directory.
@param string $outputDir The destination output directory.
@return void

### copyFromDisk()
`private function copyFromDisk(string $dir, string $base, string $outputDir): void`

Copies a directory tree from disk to the public output directory.

@param string $dir The source directory.
@param string $base The base path of the project.
@param string $outputDir The destination output directory.
@return void

### copyViewAssets()
`public function copyViewAssets(string $dir, string $base, string $outputDir): void`

Copies global assets from the `views/assets` directory to the public root.

@param string $dir The views directory.
@param string $base The base path of the project.
@param string $outputDir The destination output directory.
@return void

### copyAssetsFromDisk()
`private function copyAssetsFromDisk(string $dir, string $base, string $outputDir): void`

Recursively copies assets (.js, .css) from a theme's directory to the public output.

@param string $dir The source directory.
@param string $base The base project directory.
@param string $outputDir The destination output directory.
@return void

### garbageCollect()
`public function garbageCollect(array $manifest): void`

Scans output directories and removes files not registered in the manifest.
Removes empty directories as well.

@param array<string, bool> $manifest

### cleanOrphanedFiles()
`public function cleanOrphanedFiles(string $dir, array $manifest): void`

Recursively deletes orphaned files and empty directories.

@param array<string, bool> $manifest
