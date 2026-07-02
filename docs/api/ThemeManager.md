# ThemeManager
**Namespace:** `Indieinabox`

Class ThemeManager

Manages the inclusion of theme view files and the copying of static theme assets.
Provides fallback mechanisms to load embedded theme contents if disk files are missing.

## Methods

### loadView()
`public static function loadView(string $__tm_view_path, array $data = []): void`

Includes a view file. If the file exists on disk, it uses standard include.
Otherwise, it tries to load it from the embedded DefaultTheme fallback.

@param string $__tm_view_path
@param array<string, mixed> $data

### copyStaticFiles()
`public static function copyStaticFiles(string $dir, string $base, string $outputDir): void`

Copies static files. If the directory exists on disk, it uses file system copy.
Otherwise, it writes the embedded static files to the destination.

### copyFromDisk()
`private static function copyFromDisk(string $dir, string $base, string $outputDir): void`

### copyViewAssets()
`public static function copyViewAssets(string $dir, string $base, string $outputDir): void`

### copyAssetsFromDisk()
`private static function copyAssetsFromDisk(string $dir, string $base, string $outputDir): void`
