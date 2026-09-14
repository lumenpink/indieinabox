<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class ThemeManager
 * 
 * Manages the resolution, inclusion, and rendering of theme view templates.
 * Provides fallback mechanisms to load embedded theme contents if disk files are missing.
 */
class ThemeManager
{
    /**
     * Includes a view file. If the file exists on disk, it uses standard include.
     * Otherwise, it attempts to load and evaluate it from the embedded DefaultTheme fallback.
     *
     * @param string $__tm_view_path The path to the view template file.
     * @param array<string, mixed> $data Variables to extract into the template scope.
     * @return void
     */
    public static function loadView(string $__tm_view_path, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        if (file_exists($__tm_view_path)) {
            include $__tm_view_path;
            return;
        }

        // Try to load from embedded theme if compiled
        if (class_exists('\\DefaultTheme')) {
            $relativePath = self::resolveEmbeddedKey($__tm_view_path);
            $__tm_content = \DefaultTheme::getView($relativePath);
            if ($__tm_content !== null) {
                eval('?>' . $__tm_content);
                return;
            }
        }

        // If neither exists, output a helpful error instead of crashing silently
        echo "<!-- Theme file not found: " . htmlspecialchars($__tm_view_path) . " -->\n";
    }

    /**
     * Renders a view template and captures its output into a string.
     *
     * @param string $viewPath The path to the view template file.
     * @param array<string, mixed> $data Variables to extract into the template scope.
     * @return string The rendered template HTML.
     */
    public static function renderView(string $viewPath, array $data = []): string
    {
        ob_start();
        self::loadView($viewPath, $data);
        return (string) ob_get_clean();
    }

    /**
     * Helper to include view partials (like 'includes/head.php') properly resolving the theme path.
     *
     * @param string $relativePath The partial path relative to the theme's views directory.
     * @param array<string, mixed> $data Variables to extract into the template scope.
     * @return void
     */
    public static function includeView(string $relativePath, array $data = []): void
    {
        $fullPath = self::resolveViewPath($relativePath);
        self::loadView($fullPath, $data);
    }

    /**
     * Resolves the full filesystem path for a relative theme view.
     *
     * @param string $relativePath The view file relative to the views directory.
     * @return string The resolved path.
     */
    public static function resolveViewPath(string $relativePath): string
    {
        global $site;
        $themeDir = isset($site) && isset($site->paths->themeDir) ? $site->paths->themeDir : 'resources';
        return rtrim($themeDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . ltrim($relativePath, '/');
    }

    /**
     * Checks if a view exists either on disk or in the embedded DefaultTheme.
     *
     * @param string $viewPath
     * @return bool
     */
    public static function hasView(string $viewPath): bool
    {
        if (file_exists($viewPath)) {
            return true;
        }

        if (class_exists('\\DefaultTheme')) {
            $relativePath = self::resolveEmbeddedKey($viewPath);
            return \DefaultTheme::getView($relativePath) !== null;
        }

        return false;
    }

    /**
     * Retrieves the raw template content from disk or embedded DefaultTheme.
     *
     * @param string $viewPath
     * @return string|null
     */
    public static function getViewContent(string $viewPath): ?string
    {
        if (file_exists($viewPath)) {
            $content = file_get_contents($viewPath);
            return $content !== false ? $content : null;
        }

        if (class_exists('\\DefaultTheme')) {
            $relativePath = self::resolveEmbeddedKey($viewPath);
            return \DefaultTheme::getView($relativePath);
        }

        return null;
    }

    /**
     * Resolves the normalized embedded key for DefaultTheme lookups.
     *
     * @param string $viewPath
     * @return string
     */
    private static function resolveEmbeddedKey(string $viewPath): string
    {
        global $site;
        $themeDir = isset($site) && isset($site->paths->themeDir) ? $site->paths->themeDir : 'resources';

        $searchStr = trim($themeDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $pos = strpos($viewPath, DIRECTORY_SEPARATOR . $searchStr);
        if ($pos !== false) {
            $dirLen = strlen(DIRECTORY_SEPARATOR . $searchStr);
            $relativePath = substr($viewPath, $pos + $dirLen);
        } elseif (strpos($viewPath, $searchStr) === 0) {
            $dirLen = strlen($searchStr);
            $relativePath = substr($viewPath, $dirLen);
        } else {
            $relativePath = basename($viewPath);
        }

        return str_replace('\\', '/', $relativePath);
    }
}
