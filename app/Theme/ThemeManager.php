<?php

declare(strict_types=1);

namespace Indieinabox\Theme;

use Indieinabox\Core\Container;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;
use Indieinabox\Theme\Contracts\ThemeEcosystemInterface;

/**
 * Class ThemeManager
 * 
 * Manages the resolution, inclusion, and rendering of theme view templates,
 * as well as the Theme Ecosystem (discovery, manifest validation, ZIP/Git installation, and switching).
 */
class ThemeManager implements ThemeEcosystemInterface
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
            /** @psalm-suppress UndefinedClass */
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
        $container = \Indieinabox\Core\Container::getInstance();
        $site = $container->has(Site::class) ? $container->get(Site::class) : ($GLOBALS['site'] ?? null);
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
            /** @psalm-suppress UndefinedClass */
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
            /** @psalm-suppress UndefinedClass */
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
        $container = \Indieinabox\Core\Container::getInstance();
        $site = $container->has(Site::class) ? $container->get(Site::class) : ($GLOBALS['site'] ?? null);
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

    /**
     * Lists all installed and available themes.
     *
     * @param ?string $baseThemesDir
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    public function listThemes(?string $baseThemesDir = null): array
    {
        return self::getAllThemes($baseThemesDir);
    }

    /**
     * Static helper to list all themes.
     *
     * @param ?string $baseThemesDir
     * @return array<int, array<string, mixed>>
     */
    public static function getAllThemes(?string $baseThemesDir = null): array
    {
        $container = Container::getInstance();
        $site = $container->has(Site::class) ? $container->get(Site::class) : ($GLOBALS['site'] ?? null);
        $activeThemeDir = isset($site) && isset($site->paths->themeDir) ? $site->paths->themeDir : 'resources';
        $activeSlug = basename($activeThemeDir);
        if ($activeThemeDir === 'resources' || $activeSlug === 'resources') {
            $activeSlug = 'default';
        }

        $themes = [
            [
                'name' => 'Default Theme',
                'slug' => 'default',
                'version' => '1.0.0',
                'description' => 'Built-in default Indie in a Box theme',
                'author' => 'Indie in a Box Core',
                'license' => 'MIT',
                'path' => 'resources',
                'is_active' => $activeSlug === 'default',
            ]
        ];

        $themesDir = $baseThemesDir ?? 'themes';
        if (is_dir($themesDir)) {
            $entries = scandir($themesDir) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = rtrim($themesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($path)) {
                    continue;
                }

                $manifestPath = $path . DIRECTORY_SEPARATOR . 'theme.json';
                $manifest = [];
                if (file_exists($manifestPath)) {
                    $json = file_get_contents($manifestPath);
                    $manifest = json_decode((string) $json, true) ?: [];
                }

                $name = !empty($manifest['name']) ? (string) $manifest['name'] : ucwords(str_replace(['-', '_'], ' ', $entry));
                $version = !empty($manifest['version']) ? (string) $manifest['version'] : '1.0.0';
                $desc = !empty($manifest['description']) ? (string) $manifest['description'] : 'Custom community theme';

                $themes[] = [
                    'name' => $name,
                    'slug' => $entry,
                    'version' => $version,
                    'description' => $desc,
                    'author' => $manifest['author'] ?? 'Unknown',
                    'license' => $manifest['license'] ?? 'Custom',
                    'homepage' => $manifest['homepage'] ?? '',
                    'path' => $path,
                    'is_active' => $activeSlug === $entry,
                ];
            }
        }

        return $themes;
    }

    /**
     * Validates a theme.json manifest structure.
     *
     * @param array<string, mixed> $manifest
     * @return array{valid: bool, errors: list<string>}
     */
    #[\Override]
    public function validateManifest(array $manifest): array
    {
        return self::checkManifest($manifest);
    }

    /**
     * Static helper to validate a theme manifest.
     *
     * @param array<string, mixed> $manifest
     * @return array{valid: bool, errors: list<string>}
     */
    public static function checkManifest(array $manifest): array
    {
        $errors = [];

        if (empty($manifest['name']) || !is_string($manifest['name'])) {
            $errors[] = 'Theme name is required and must be a non-empty string.';
        }

        if (empty($manifest['version']) || !is_string($manifest['version'])) {
            $errors[] = 'Theme version is required and must be a non-empty string.';
        }

        if (isset($manifest['templates']) && is_array($manifest['templates'])) {
            foreach ($manifest['templates'] as $tpl) {
                if (is_string($tpl) && (str_contains($tpl, '..') || str_starts_with($tpl, '/'))) {
                    $errors[] = 'Template path contains forbidden directory traversal characters: ' . $tpl;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Installs a theme from a ZIP package file.
     *
     * @param string $zipPath
     * @param ?string $baseThemesDir
     * @return array{success: bool, slug?: string, manifest?: array<string, mixed>, error?: string}
     */
    #[\Override]
    public function installFromZip(string $zipPath, ?string $baseThemesDir = null): array
    {
        if (!file_exists($zipPath)) {
            return ['success' => false, 'error' => 'ZIP archive file does not exist: ' . $zipPath];
        }

        $themesDir = $baseThemesDir ?? 'themes';
        if (!is_dir($themesDir)) {
            @mkdir($themesDir, 0755, true);
        }

        $tempDir = sys_get_temp_dir() . '/iiab_theme_extract_' . uniqid();
        @mkdir($tempDir, 0755, true);

        $extracted = false;
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $extracted = $zip->extractTo($tempDir);
                $zip->close();
            }
        }

        if (!$extracted) {
            $cmd = sprintf('unzip -q -o %s -d %s 2>&1', escapeshellarg($zipPath), escapeshellarg($tempDir));
            exec($cmd, $out, $ret);
            $extracted = ($ret === 0);
        }

        if (!$extracted) {
            FileUtils::recursiveRmdir($tempDir);
            return ['success' => false, 'error' => 'Failed to extract ZIP archive using ZipArchive or unzip command.'];
        }

        $scan = array_values(array_filter(scandir($tempDir) ?: [], fn($f) => !in_array($f, ['.', '..'])));
        $sourceDir = $tempDir;
        if (count($scan) === 1 && is_dir($tempDir . DIRECTORY_SEPARATOR . $scan[0])) {
            $sourceDir = $tempDir . DIRECTORY_SEPARATOR . $scan[0];
            $candidateSlug = $scan[0];
        } else {
            $candidateSlug = pathinfo($zipPath, PATHINFO_FILENAME);
        }

        $manifest = [];
        $manifestPath = $sourceDir . DIRECTORY_SEPARATOR . 'theme.json';
        if (file_exists($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
            $check = self::checkManifest($manifest);
            if (!$check['valid']) {
                FileUtils::recursiveRmdir($tempDir);
                return ['success' => false, 'error' => implode(' ', $check['errors'])];
            }
        }

        $slug = !empty($manifest['slug']) ? (string) $manifest['slug'] : $candidateSlug;
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '-', $slug) ?: 'custom-theme');
        $destPath = rtrim($themesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $slug;

        if (is_dir($destPath)) {
            FileUtils::recursiveRmdir($destPath);
        }
        @mkdir($destPath, 0755, true);

        $copyCmd = sprintf('cp -rf %s/. %s/ 2>&1', escapeshellarg($sourceDir), escapeshellarg($destPath));
        exec($copyCmd, $cpOut, $cpRet);

        FileUtils::recursiveRmdir($tempDir);

        if ($cpRet !== 0) {
            return ['success' => false, 'error' => 'Failed to copy extracted theme files to destination.'];
        }

        return [
            'success' => true,
            'slug' => $slug,
            'manifest' => $manifest ?: ['name' => $slug, 'version' => '1.0.0'],
        ];
    }

    /**
     * Installs a theme from a Git repository.
     *
     * @param string $gitUrl
     * @param ?string $targetSlug
     * @param ?string $baseThemesDir
     * @return array{success: bool, slug?: string, manifest?: array<string, mixed>, error?: string}
     */
    #[\Override]
    public function installFromGit(string $gitUrl, ?string $targetSlug = null, ?string $baseThemesDir = null): array
    {
        if (!preg_match('#^(https?|git)://#i', $gitUrl)) {
            return ['success' => false, 'error' => 'Invalid Git repository URL. Must begin with http://, https:// or git://'];
        }

        $themesDir = $baseThemesDir ?? 'themes';
        if (!is_dir($themesDir)) {
            @mkdir($themesDir, 0755, true);
        }

        if ($targetSlug === null) {
            $pathPart = parse_url($gitUrl, PHP_URL_PATH) ?? 'theme';
            $baseName = basename($pathPart);
            $targetSlug = preg_replace('/\.git$/i', '', $baseName);
        }

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '-', $targetSlug) ?: 'custom-theme');
        $destPath = rtrim($themesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $slug;

        if (is_dir($destPath)) {
            FileUtils::recursiveRmdir($destPath);
        }

        $cmd = sprintf('git clone --depth 1 %s %s 2>&1', escapeshellarg($gitUrl), escapeshellarg($destPath));
        exec($cmd, $out, $ret);

        if ($ret !== 0) {
            return ['success' => false, 'error' => 'Git clone failed: ' . implode("\n", $out)];
        }

        $gitFolder = $destPath . DIRECTORY_SEPARATOR . '.git';
        if (is_dir($gitFolder)) {
            FileUtils::recursiveRmdir($gitFolder);
        }

        $manifest = [];
        $manifestPath = $destPath . DIRECTORY_SEPARATOR . 'theme.json';
        if (file_exists($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
        }

        return [
            'success' => true,
            'slug' => $slug,
            'manifest' => $manifest ?: ['name' => $slug, 'version' => '1.0.0'],
        ];
    }

    /**
     * Activates a theme.
     *
     * @param string $slug
     * @param ?Site $site
     * @param ?string $baseThemesDir
     * @return bool
     */
    #[\Override]
    public function activateTheme(string $slug, ?Site $site = null, ?string $baseThemesDir = null): bool
    {
        $themesDir = $baseThemesDir ?? 'themes';
        $targetDir = $slug === 'default'
            ? 'resources'
            : rtrim($themesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $slug;

        if ($slug !== 'default' && !is_dir($targetDir)) {
            return false;
        }

        if ($site !== null && isset($site->paths)) {
            $site->paths->themeDir = $targetDir;
        }

        if (class_exists(\Indieinabox\Core\Database::class) && \Indieinabox\Core\Database::isConnected()) {
            \Indieinabox\Core\Database::saveSetting('theme', $slug);
        }

        return true;
    }
}
