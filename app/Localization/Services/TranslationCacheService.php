<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Services;

use Indieinabox\Core\Database;

/**
 * Caches translated strings and post content by content hash, source/target languages, and provider.
 * Prevents redundant external API calls and token waste during builds.
 */
class TranslationCacheService
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        if ($cacheDir !== null) {
            $this->cacheDir = $cacheDir;
        } else {
            $dataDir = !empty(Database::$dataDir) ? Database::$dataDir : '';
            if ($dataDir === '' || !is_dir($dataDir)) {
                if (is_dir('/app/data')) {
                    $dataDir = '/app/data';
                } elseif (is_dir(getcwd() . '/data')) {
                    $dataDir = getcwd() . '/data';
                } else {
                    $dataDir = sys_get_temp_dir() . '/indieinabox_data';
                }
            }
            $this->cacheDir = $dataDir . '/cache/translations';
        }

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Computes unique hash key for a translation request.
     */
    public function makeKey(string $content, string $targetLang, string $sourceLang, string $provider, string $model = ''): string
    {
        return hash('sha256', $content . '|' . $sourceLang . '|' . $targetLang . '|' . $provider . '|' . $model);
    }

    /**
     * Gets a cached translation if available.
     */
    public function get(string $key): ?string
    {
        $filePath = $this->cacheDir . '/' . $key . '.cache';
        if (is_file($filePath)) {
            $val = @file_get_contents($filePath);
            return $val !== false ? $val : null;
        }
        return null;
    }

    /**
     * Stores a translation in cache.
     */
    public function put(string $key, string $translatedContent): bool
    {
        $filePath = $this->cacheDir . '/' . $key . '.cache';
        return @file_put_contents($filePath, $translatedContent) !== false;
    }

    /**
     * Clears all cached translations.
     */
    public function flush(): void
    {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*.cache');
            if (is_array($files)) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
        }
    }
}
