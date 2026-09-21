<?php

declare(strict_types=1);

namespace Indieinabox\Localization;

use Indieinabox\Core\Database;

/**
 * Service to resolve, download, and apply localized translation dictionaries and taxonomy presets.
 * Locales are kept in the official repository and downloaded on demand, caching locally in data/locales.
 */
class LocaleManager
{
    public const REPO_BASE_URL = 'https://codeberg.org/indieinabox/indieinabox/raw/branch/main/resources/locales';

    /**
     * Resolves a locale dictionary for a language code (e.g. 'pt', 'es', 'pt-BR').
     * Checks local caches first (data/locales/ or resources/locales/), and falls back
     * to downloading from the remote repository on demand.
     *
     * @return array{code: string, name: string, translations: array<string, string>, kinds?: array<string, array{title: string, content_dir: string}>}|null
     */
    public static function getLocale(string $lang, bool $allowRemote = true): ?array
    {
        $normalized = strtolower(trim($lang));
        if ($normalized === '') {
            return null;
        }

        // Try exact match then primary subtag (e.g. 'pt-br' -> 'pt')
        $candidates = [$normalized];
        if (str_contains($normalized, '-')) {
            $candidates[] = explode('-', $normalized)[0];
        }

        // 1. Check local files first (cached in data/locales or development in resources/locales)
        foreach ($candidates as $code) {
            $data = self::loadLocal($code);
            if ($data !== null) {
                return $data;
            }
        }

        // 2. Try remote repository download on demand if no local match was found
        if ($allowRemote) {
            foreach ($candidates as $code) {
                $url = self::REPO_BASE_URL . '/' . $code . '.json';
                $remoteData = self::downloadRemote($url);
                if ($remoteData !== null) {
                    $cacheDir = self::getCacheDir();
                    if (!is_dir($cacheDir)) {
                        @mkdir($cacheDir, 0755, true);
                    }
                    if (is_dir($cacheDir) && is_writable($cacheDir)) {
                        @file_put_contents($cacheDir . '/' . $code . '.json', (string) json_encode($remoteData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                    return $remoteData;
                }
            }
        }

        return null;
    }

    /**
     * Applies locale translations and kind configurations to a settings array in-place.
     *
     * @param array<string, mixed> $config
     */
    public static function applyLocale(array &$config, string $lang, bool $overwriteExisting = false): bool
    {
        $locale = self::getLocale($lang);
        if ($locale === null) {
            return false;
        }

        if (!isset($config['translations']) || !is_array($config['translations'])) {
            $config['translations'] = [];
        }

        foreach ($locale['translations'] as $key => $val) {
            if (!isset($config['translations'][$key]) || !is_array($config['translations'][$key])) {
                $config['translations'][$key] = [];
            }
            if ($overwriteExisting || !isset($config['translations'][$key][$lang]) || $config['translations'][$key][$lang] === '') {
                $config['translations'][$key][$lang] = $val;
            }
        }

        if (!empty($locale['kinds']) && isset($config['kinds']) && is_array($config['kinds'])) {
            foreach ($locale['kinds'] as $kindKey => $kindInfo) {
                if (isset($config['kinds'][$kindKey]) && is_array($config['kinds'][$kindKey])) {
                    if (isset($kindInfo['title'])) {
                        if (!isset($config['kinds'][$kindKey]['title']) || !is_array($config['kinds'][$kindKey]['title'])) {
                            $config['kinds'][$kindKey]['title'] = [];
                        }
                        if ($overwriteExisting || empty($config['kinds'][$kindKey]['title'][$lang])) {
                            $config['kinds'][$kindKey]['title'][$lang] = $kindInfo['title'];
                        }
                    }
                    if (isset($kindInfo['content_dir'])) {
                        if (!isset($config['kinds'][$kindKey]['content_dir']) || !is_array($config['kinds'][$kindKey]['content_dir'])) {
                            $config['kinds'][$kindKey]['content_dir'] = [];
                        }
                        if ($overwriteExisting || empty($config['kinds'][$kindKey]['content_dir'][$lang])) {
                            $config['kinds'][$kindKey]['content_dir'][$lang] = $kindInfo['content_dir'];
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * Returns a list of all currently available local and cached locale codes.
     *
     * @return array<int, string>
     */
    public static function getSupportedLocales(): array
    {
        $locales = [];
        $baseDir = dirname(__DIR__, 2);
        $directories = [
            self::getCacheDir(),
            $baseDir . '/resources/locales',
        ];

        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*.json');
                if ($files) {
                    foreach ($files as $file) {
                        $code = basename($file, '.json');
                        if (!in_array($code, $locales, true)) {
                            $locales[] = $code;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($locales));
    }

    /**
     * Resolves the local cache directory for on-demand locale downloads.
     */
    public static function getCacheDir(): string
    {
        $dataDir = !empty(Database::$dataDir) ? Database::$dataDir : (dirname(__DIR__, 2) . '/data');
        return $dataDir . '/locales';
    }

    /**
     * Checks local files (data/locales/ or resources/locales/).
     *
     * @return array{code: string, name: string, translations: array<string, string>, kinds?: array<string, array{title: string, content_dir: string}>}|null
     */
    private static function loadLocal(string $code): ?array
    {
        $baseDir = dirname(__DIR__, 2);
        $paths = [
            self::getCacheDir() . '/' . $code . '.json',
            $baseDir . '/resources/locales/' . $code . '.json',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $raw = (string) file_get_contents($path);
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['translations'])) {
                    /** @var array{code: string, name: string, translations: array<string, string>, kinds?: array<string, array{title: string, content_dir: string}>} $decoded */
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * Downloads and parses a remote JSON file with short timeout.
     *
     * @return array{code: string, name: string, translations: array<string, string>, kinds?: array<string, array{title: string, content_dir: string}>}|null
     */
    private static function downloadRemote(string $url): ?array
    {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 2.0,
                    'user_agent' => 'Indieinabox-LocaleManager/1.0',
                    'ignore_errors' => true,
                ],
            ]);
            $content = @file_get_contents($url, false, $ctx);
            if ($content !== false && $content !== '') {
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['translations'])) {
                    /** @var array{code: string, name: string, translations: array<string, string>, kinds?: array<string, array{title: string, content_dir: string}>} $decoded */
                    return $decoded;
                }
            }
        } catch (\Throwable) {
            // Ignore network or SSL errors
        }

        return null;
    }
}
