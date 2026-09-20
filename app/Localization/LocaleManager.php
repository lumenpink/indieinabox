<?php

declare(strict_types=1);

namespace Indieinabox\Localization;

/**
 * Service to resolve, download, and apply localized translation dictionaries and taxonomy presets.
 */
class LocaleManager
{
    public const REPO_BASE_URL = 'https://codeberg.org/indieinabox/indieinabox/raw/branch/main/resources/locales';

    /**
     * In-memory bundled fallback dictionaries for standalone binary execution and offline environments.
     *
     * @var array<string, array{code: string, name: string, translations: array<string, string>, kinds: array<string, array{title: string, content_dir: string}>}>
     */
    private static array $bundledLocales = [
        'pt' => [
            'code' => 'pt',
            'name' => 'Português',
            'translations' => [
                'Home' => 'Início',
                'Index' => 'Índice',
                'Now' => 'Agora',
                'Recent posts' => 'Publicações recentes',
                'Browse the sections of the site in Gopher style:' => 'Navegue pelas seções do site no estilo Gopher:',
                'About' => 'Sobre',
                'Maturity' => 'Maturidade',
                'Reliability' => 'Confiabilidade',
                'Shortlink' => 'Link curto',
                'Like' => 'Curtida',
                'Likes' => 'Curtidas',
                'Repost' => 'Compartilhamento',
                'Reposts' => 'Compartilhamentos',
                'Reply' => 'Resposta',
                'Replies' => 'Respostas',
                'Interactions on' => 'Interações em',
                'Permalink' => 'Link permanente',
                'Flowerbed' => 'Canteiro',
                'Confidence' => 'Confiança',
                'Importance' => 'Importância',
                'Also on' => 'Também em',
                'In reply to' => 'Em resposta a',
                'Liked' => 'Curtiu',
                'Reposted' => 'Compartilhou',
                'Bookmarked' => 'Salvou',
                'Watched' => 'Assistiu',
                'Read' => 'Leu',
                'Listened to' => 'Ouviu',
                'This page was automatically translated by AI.' => 'Esta página foi traduzida automaticamente por IA.',
                'This page was automatically translated by AI and revised by a human.' => 'Esta página foi traduzida automaticamente por IA e revisada por um humano.',
                'Read more' => 'Leia mais',
                'general' => 'geral',
                'certain' => 'certo',
                'likely' => 'provável',
                'possible' => 'possível',
                'unlikely' => 'improvável',
                'impossible' => 'impossível',
                'sprout' => 'broto',
                'seedling' => 'muda',
                'tree' => 'árvore',
                'wilted' => 'murcha',
                'stone' => 'pedra',
                'trivial' => 'trivial',
                'minor' => 'menor',
                'moderate' => 'moderada',
                'major' => 'maior',
                'critical' => 'crítica',
                'unknown' => 'desconhecido',
                'Tag' => 'Tag',
                'Tags' => 'Tags',
                'Flowerbeds' => 'Canteiros',
            ],
            'kinds' => [
                'article' => ['title' => 'Artigos', 'content_dir' => 'artigos'],
                'note' => ['title' => 'Notas', 'content_dir' => 'notas'],
            ],
        ],
        'es' => [
            'code' => 'es',
            'name' => 'Español',
            'translations' => [
                'Home' => 'Inicio',
                'Index' => 'Índice',
                'Now' => 'Ahora',
                'Recent posts' => 'Publicaciones recientes',
                'Browse the sections of the site in Gopher style:' => 'Explore las secciones del sitio al estilo Gopher:',
                'About' => 'Acerca de',
                'Maturity' => 'Madurez',
                'Reliability' => 'Confiabilidad',
                'Shortlink' => 'Enlace corto',
                'Like' => 'Me gusta',
                'Likes' => 'Me gusta',
                'Repost' => 'Compartido',
                'Reposts' => 'Compartidos',
                'Reply' => 'Respuesta',
                'Replies' => 'Respuestas',
                'Interactions on' => 'Interacciones en',
                'Permalink' => 'Enlace permanente',
                'Flowerbed' => 'Semillero',
                'Confidence' => 'Confianza',
                'Importance' => 'Importancia',
                'Also on' => 'También en',
                'In reply to' => 'En respuesta a',
                'Liked' => 'Le gustó',
                'Reposted' => 'Compartió',
                'Bookmarked' => 'Guardó',
                'Watched' => 'Vio',
                'Read' => 'Leyó',
                'Listened to' => 'Escuchó',
                'This page was automatically translated by AI.' => 'Esta página fue traducida automáticamente por IA.',
                'This page was automatically translated by AI and revised by a human.' => 'Esta página fue traducida automáticamente por IA e revisada por un humano.',
                'Read more' => 'Leer más',
                'general' => 'general',
                'certain' => 'seguro',
                'likely' => 'probable',
                'possible' => 'posible',
                'unlikely' => 'improbable',
                'impossible' => 'imposible',
                'sprout' => 'brote',
                'seedling' => 'plántula',
                'tree' => 'árbol',
                'wilted' => 'marchita',
                'stone' => 'piedra',
                'trivial' => 'trivial',
                'minor' => 'menor',
                'moderate' => 'moderada',
                'major' => 'mayor',
                'critical' => 'crítica',
                'unknown' => 'desconocido',
                'Tag' => 'Etiqueta',
                'Tags' => 'Etiquetas',
                'Flowerbeds' => 'Semilleros',
            ],
            'kinds' => [
                'article' => ['title' => 'Artículos', 'content_dir' => 'articulos'],
                'note' => ['title' => 'Notas', 'content_dir' => 'notas'],
            ],
        ],
        'en' => [
            'code' => 'en',
            'name' => 'English',
            'translations' => [
                'Home' => 'Home',
                'Index' => 'Index',
                'Now' => 'Now',
                'Recent posts' => 'Recent posts',
                'Browse the sections of the site in Gopher style:' => 'Browse the sections of the site in Gopher style:',
                'About' => 'About',
                'Maturity' => 'Maturity',
                'Reliability' => 'Reliability',
                'Shortlink' => 'Shortlink',
                'Like' => 'Like',
                'Likes' => 'Likes',
                'Repost' => 'Repost',
                'Reposts' => 'Reposts',
                'Reply' => 'Reply',
                'Replies' => 'Replies',
                'Interactions on' => 'Interactions on',
                'Permalink' => 'Permalink',
                'Flowerbed' => 'Flowerbed',
                'Confidence' => 'Confidence',
                'Importance' => 'Importance',
                'Also on' => 'Also on',
                'In reply to' => 'In reply to',
                'Liked' => 'Liked',
                'Reposted' => 'Reposted',
                'Bookmarked' => 'Bookmarked',
                'Watched' => 'Watched',
                'Read' => 'Read',
                'Listened to' => 'Listened to',
                'This page was automatically translated by AI.' => 'This page was automatically translated by AI.',
                'This page was automatically translated by AI and revised by a human.' => 'This page was automatically translated by AI and revised by a human.',
                'Read more' => 'Read more',
                'general' => 'general',
                'certain' => 'certain',
                'likely' => 'likely',
                'possible' => 'possible',
                'unlikely' => 'unlikely',
                'impossible' => 'impossible',
                'sprout' => 'sprout',
                'seedling' => 'seedling',
                'tree' => 'tree',
                'wilted' => 'wilted',
                'stone' => 'stone',
                'trivial' => 'trivial',
                'minor' => 'minor',
                'moderate' => 'moderate',
                'major' => 'major',
                'critical' => 'critical',
                'unknown' => 'unknown',
                'Tag' => 'Tag',
                'Tags' => 'Tags',
                'Flowerbeds' => 'Flowerbeds',
            ],
            'kinds' => [
                'article' => ['title' => 'Articles', 'content_dir' => 'articles'],
                'note' => ['title' => 'Notes', 'content_dir' => 'notes'],
            ],
        ],
    ];

    /**
     * Resolves a locale dictionary for a language code (e.g. 'pt', 'es', 'pt-BR').
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

        // 1. Check local files and bundled presets first for all candidates (fast, offline)
        foreach ($candidates as $code) {
            $data = self::loadLocalOrBundled($code);
            if ($data !== null) {
                return $data;
            }
        }

        // 2. Try remote repository download only if no local/bundled match was found
        if ($allowRemote) {
            foreach ($candidates as $code) {
                $url = self::REPO_BASE_URL . '/' . $code . '.json';
                $remoteData = self::downloadRemote($url);
                if ($remoteData !== null) {
                    $baseDir = dirname(__DIR__, 2);
                    $cacheDir = $baseDir . '/data/locales';
                    if (!is_dir($cacheDir)) {
                        @mkdir($cacheDir, 0755, true);
                    }
                    if (is_dir($cacheDir) && is_writable($cacheDir)) {
                        @file_put_contents($cacheDir . '/' . $code . '.json', json_encode($remoteData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
     * Returns a list of all currently supported locale codes.
     *
     * @return array<int, string>
     */
    public static function getSupportedLocales(): array
    {
        $locales = array_keys(self::$bundledLocales);

        $dir = dirname(__DIR__, 2) . '/resources/locales';
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

        return array_values($locales);
    }

    /**
     * Checks local files and bundled presets.
     *
     * @return array{code: string, name: string, translations: array<string, string>, kinds?: array<string, array{title: string, content_dir: string}>}|null
     */
    private static function loadLocalOrBundled(string $code): ?array
    {
        $baseDir = dirname(__DIR__, 2);
        $paths = [
            $baseDir . '/resources/locales/' . $code . '.json',
            $baseDir . '/data/locales/' . $code . '.json',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $raw = (string) file_get_contents($path);
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['translations'])) {
                    return $decoded;
                }
            }
        }

        return self::$bundledLocales[$code] ?? null;
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
                    return $decoded;
                }
            }
        } catch (\Throwable) {
            // Ignore network or SSL errors
        }

        return null;
    }
}
