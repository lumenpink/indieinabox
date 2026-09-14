# ThemeManager
**Namespace:** `Indieinabox`

Class ThemeManager

Manages the resolution, inclusion, and rendering of theme view templates.
Provides fallback mechanisms to load embedded theme contents if disk files are missing.

## Methods

### loadView()
`public static function loadView(string $__tm_view_path, array $data = []): void`

Includes a view file. If the file exists on disk, it uses standard include.
Otherwise, it attempts to load and evaluate it from the embedded DefaultTheme fallback.

@param string $__tm_view_path The path to the view template file.
@param array<string, mixed> $data Variables to extract into the template scope.
@return void

### renderView()
`public static function renderView(string $viewPath, array $data = []): string`

Renders a view template and captures its output into a string.

@param string $viewPath The path to the view template file.
@param array<string, mixed> $data Variables to extract into the template scope.
@return string The rendered template HTML.

### includeView()
`public static function includeView(string $relativePath, array $data = []): void`

Helper to include view partials (like 'includes/head.php') properly resolving the theme path.

@param string $relativePath The partial path relative to the theme's views directory.
@param array<string, mixed> $data Variables to extract into the template scope.
@return void

### resolveViewPath()
`public static function resolveViewPath(string $relativePath): string`

Resolves the full filesystem path for a relative theme view.

@param string $relativePath The view file relative to the views directory.
@return string The resolved path.

### hasView()
`public static function hasView(string $viewPath): bool`

Checks if a view exists either on disk or in the embedded DefaultTheme.

@param string $viewPath
@return bool

### getViewContent()
`public static function getViewContent(string $viewPath): ?string`

Retrieves the raw template content from disk or embedded DefaultTheme.

@param string $viewPath
@return string|null

### resolveEmbeddedKey()
`private static function resolveEmbeddedKey(string $viewPath): string`

Resolves the normalized embedded key for DefaultTheme lookups.

@param string $viewPath
@return string
