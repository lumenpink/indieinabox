# AssetPublisher
**Namespace:** `Indieinabox\SiteBuilder`

Class AssetPublisher

Responsible for publishing static files, theme view assets (CSS/JS), media files,
and running the build garbage collector to remove orphaned files from previous builds.

## Methods

### __construct()
```php
public function __construct(Site $site)
```
Initializes the asset publisher with site settings.

### publishViewAssets()
```php
public function publishViewAssets(string $dir): void
```
Copies theme view assets (CSS/JS files found in the views directory or embedded `DefaultTheme`)
to `public_html/assets/` and registers them in the build manifest.

### publishStaticFiles()
```php
public function publishStaticFiles(string $dir): bool
```
Copies general static files from the theme's static folder (or embedded `DefaultTheme`)
directly to the HTML output root.

### publishMedia()
```php
public function publishMedia(): void
```
Copies media files from `content/media/` and `data/microsub/media/` to the media output directory.

### garbageCollect()
```php
public function garbageCollect(array $manifest): void
```
Scans output directories (`public_html`, `public_gemini`, `public_gopher`, `public_media`)
and removes any files that were not registered in `$manifest` during the build, cleaning empty directories.
