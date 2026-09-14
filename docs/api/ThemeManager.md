# ThemeManager
**Namespace:** `Indieinabox`

Class ThemeManager

Manages the resolution, inclusion, and rendering of theme view templates.
Provides fallback mechanisms to load embedded theme contents if disk files are missing.

## Methods

### loadView()
```php
public static function loadView(string $__tm_view_path, array $data = []): void
```
Includes a view file. If the file exists on disk, it uses standard include.
Otherwise, it attempts to load and evaluate it from the embedded `DefaultTheme` fallback.

### renderView()
```php
public static function renderView(string $viewPath, array $data = []): string
```
Renders a view template with extracted `$data` and captures its buffered output, returning the rendered HTML string.

### includeView()
```php
public static function includeView(string $relativePath, array $data = []): void
```
Helper to include view partials (like `includes/head.php` or `includes/header.php`) relative to the theme's views directory.

### resolveViewPath()
```php
public static function resolveViewPath(string $relativePath): string
```
Resolves the full filesystem path for a relative theme view based on `$site->paths->themeDir`.

### hasView()
```php
public static function hasView(string $viewPath): bool
```
Checks if a view exists either on disk or in the embedded `DefaultTheme`.

### getViewContent()
```php
public static function getViewContent(string $viewPath): ?string
```
Retrieves the raw PHP/HTML template content from disk or embedded `DefaultTheme`.
