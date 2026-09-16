<?php

declare(strict_types=1);

namespace Indieinabox\Taxonomy;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Page\Page;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Site\Site;
use Indieinabox\Support\TextParser;
use Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface;

/**
 * Class TaxonomyService
 *
 * Domain service managing post kind taxonomy, slug-to-kind mapping,
 * localized folder resolution, and menu links.
 */
class TaxonomyService implements TaxonomyServiceInterface
{
    private ?Site $site;
    private ?SettingsRepositoryInterface $settingsRepo;

    public function __construct(?Site $site = null, ?SettingsRepositoryInterface $settingsRepo = null)
    {
        $container = class_exists(Container::class) ? Container::getInstance() : null;

        $this->site = $site ?? ($container && $container->has(Site::class)
            ? $container->get(Site::class)
            : ($GLOBALS['site'] ?? null));

        $this->settingsRepo = $settingsRepo ?? ($container && $container->has(SettingsRepositoryInterface::class)
            ? $container->get(SettingsRepositoryInterface::class)
            : null);
    }

    private function getSite(): ?Site
    {
        if ($this->site !== null) {
            return $this->site;
        }
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        return $container && $container->has(Site::class) ? $container->get(Site::class) : ($GLOBALS['site'] ?? null);
    }

    /**
     * Retrieves kind configuration with sensible defaults.
     *
     * @param string $kind
     * @return array<string, mixed>
     */
    public function getKindConfig(string $kind): array
    {
        $site = $this->getSite();
        $kind = strtolower($kind);

        if ($site && !empty($site->config['kinds'])) {
            $config = $site->config['kinds'][$kind] ?? [];
        } else {
            $kinds = $this->settingsRepo ? $this->settingsRepo->getKinds() : Database::getKinds();
            if ($site) {
                $site->config['kinds'] = $kinds;
            }
            $config = $kinds[$kind] ?? [];
        }

        if (empty($config['content_dir'])) {
            $kindsPath = $site ? ($site->config['kindspath'] ?? null) : null;
            if ($kindsPath === null || empty($kindsPath)) {
                $kindsPath = !empty($GLOBALS['kindspath'])
                    ? $GLOBALS['kindspath']
                    : ($this->settingsRepo ? $this->settingsRepo->get('kindspath', []) : Database::getSetting('kindspath', []));
            }
            if (!empty($kindsPath[$kind])) {
                $config['content_dir'] = is_array($kindsPath[$kind]) ? reset($kindsPath[$kind]) : $kindsPath[$kind];
            }
        }

        $config = is_array($config) ? $config : [];

        return array_merge([
            'content_dir' => $kind,
            'title' => [],
            'palette' => null,
            'has_title' => true,
            'show_on_home' => false,
            'display_mode' => 'default',
        ], $config);
    }

    /**
     * Determines the kind and localized folder for a page.
     *
     * @param Page|array<string, mixed> $page
     * @return array{localized: string, kind: string}
     */
    public function resolveKind(mixed $page): array
    {
        $site = $this->getSite();
        $isObject = $page instanceof Page;
        $pageKind = $isObject ? $page->kind : ($page['kind'] ?? null);
        $pageSlug = $isObject ? $page->slug : ($page['slug'] ?? '');
        $pageLang = $isObject ? $page->lang : ($page['lang'] ?? 'en');

        if ($pageKind !== null && $pageKind !== '') {
            $kind = $pageKind;
            $kindConfig = $site->config['kinds'][$kind] ?? null;
            if ($kindConfig) {
                $localizedkind = $this->getKindFolder($kind, $pageLang);
            } else {
                $localizedkind = $kind;
            }
        } else {
            $localizedkindSegment = explode('/', $pageSlug);
            if ($site && $pageLang === $site->defaultlang) {
                $localizedkindSegment = $localizedkindSegment[0];
            } else {
                $localizedkindSegment = $localizedkindSegment[1] ?? $localizedkindSegment[0];
            }

            // Resolve kind from config content_dir
            if ($site && !empty($site->config['kinds'])) {
                foreach ($site->config['kinds'] as $k => $conf) {
                    $cDir = $conf['content_dir'] ?? $k;
                    if (is_array($cDir)) {
                        if (in_array($localizedkindSegment, $cDir, true)) {
                            $kind = $k;
                            break;
                        }
                    } elseif ($cDir === $localizedkindSegment) {
                        $kind = $k;
                        break;
                    }
                }
            }

            // Fallback to kindspath setting if not found
            if (!isset($kind)) {
                $kindsPath = $site ? ($site->config['kindspath'] ?? null) : null;
                if ($kindsPath === null || empty($kindsPath)) {
                    $kindsPath = !empty($GLOBALS['kindspath'])
                        ? $GLOBALS['kindspath']
                        : ($this->settingsRepo ? $this->settingsRepo->get('kindspath', []) : Database::getSetting('kindspath', []));
                }
                if (!empty($kindsPath)) {
                    foreach ($kindsPath as $key => $value) {
                        if (in_array($localizedkindSegment, (array) $value, true)) {
                            $kind = $key;
                            break;
                        }
                    }
                }
            }

            if (!isset($kind)) {
                $isRoot = false;
                if ($isObject && $site) {
                    if (isset($site->paths) && method_exists($site->paths, 'getContentPath')) {
                        $contentPath = $site->paths->getContentPath();
                    } else {
                        $baseDir = $site->paths->baseDir ?? '';
                        $contentDir = $site->paths->contentDir ?? 'content';
                        $contentPath = $baseDir . DIRECTORY_SEPARATOR . $contentDir;
                    }
                    $relPath = str_replace($contentPath, '', $page->filepath ?? '');
                    $relPath = trim($relPath, DIRECTORY_SEPARATOR);

                    $segments = explode(DIRECTORY_SEPARATOR, $relPath);
                    $langs = $site->localization->lang ?? [];
                    if (!is_array($langs)) {
                        $langs = [$langs];
                    }
                    if (count($segments) === 1) {
                        $isRoot = true;
                    } elseif (count($segments) === 2 && in_array($segments[0], $langs, true)) {
                        $isRoot = true;
                    }
                }
                $kind = $isRoot ? 'page' : 'generic';
                $localizedkind = 'generic';
            } else {
                $kindConfig = $site->config['kinds'][$kind] ?? null;
                if ($kindConfig) {
                    $localizedkind = $this->getKindFolder($kind, $pageLang);
                } else {
                    $localizedkind = $localizedkindSegment;
                }
            }
        }
        return [
            'localized' => $localizedkind,
            'kind' => $kind,
        ];
    }

    /**
     * Get the localized folder name for a specific kind and language.
     *
     * @param string $kind
     * @param string $lang
     * @return string
     */
    public function getKindFolder(string $kind, string $lang): string
    {
        $config = $this->getKindConfig($kind);

        // If it's a special system kind (generic, page, home) and has no config, just return the kind itself
        if (in_array($kind, ['generic', 'page', 'home'], true) && empty($config['title'])) {
            return $kind;
        }

        // Use content_dir if defined
        if (isset($config['content_dir'])) {
            if (is_array($config['content_dir'])) {
                $folder = $config['content_dir'][$lang] ?? reset($config['content_dir']);
                if ($folder) {
                    return TextParser::slugize((string) $folder);
                }
            } else {
                return TextParser::slugize((string) $config['content_dir']);
            }
        }

        // Fallback to the kind itself
        return TextParser::slugize($kind);
    }

    /**
     * Return a human-readable, localized display label for a post kind.
     *
     * @param string $kind Internal kind slug
     * @param string|null $lang Target language (defaults to current page lang)
     * @return string
     */
    public function getKindLabel(string $kind, ?string $lang = null): string
    {
        $site = $this->getSite();
        $config = $this->getKindConfig($kind);
        $targetLang = $lang ?? $site->localization->defaultLang ?? 'en';

        if (!empty($config['title']) && is_array($config['title'])) {
            if (isset($config['title'][$targetLang])) {
                return $config['title'][$targetLang];
            }
            if (isset($config['title']['en'])) {
                return $config['title']['en'];
            }
            return (string) reset($config['title']);
        }

        return ucfirst($kind);
    }

    /**
     * Return a hyperlinked, human-readable display label for a post kind.
     *
     * @param Page $page The page context where this link is rendered
     * @param string $kind Internal kind slug
     * @return string
     */
    public function getKindLink(Page $page, string $kind): string
    {
        $site = $this->getSite();
        $lang = $page->lang ?? $site->localization->defaultLang ?? 'en';
        $defaultLang = $site->localization->defaultLang ?? 'en';
        $prettylinks = $site->options->prettylinks ?? true;

        $label = $this->getKindLabel($kind, $lang);

        // If it's a generic kind or hidden from menu, don't link it
        $isHidden = false;
        if (isset($site->config['kinds'][$kind]['show_in_menu']) && !$site->config['kinds'][$kind]['show_in_menu']) {
            $isHidden = true;
        }

        if ($isHidden || in_array($kind, ['generic', 'home', 'page'], true)) {
            return '[' . strtoupper(htmlspecialchars($label)) . ']';
        }

        $folder = $this->getKindFolder($kind, $lang);
        $langPrefix = ($lang === $defaultLang) ? '' : $lang . '/';

        if ($prettylinks) {
            $url = ltrim($langPrefix . $folder . '/', '/');
        } else {
            $url = ltrim($langPrefix . $folder . '.html', '/');
        }

        $relUrl = $page->relpath . $url;

        return '<a href="' . $relUrl . '">[' . strtoupper(htmlspecialchars($label)) . ']</a>';
    }

    /**
     * Get original content slug translation.
     *
     * @param string $slug
     * @param string $lang
     * @return string
     */
    public function getOriginalContent(string $slug, string $lang): string
    {
        $site = $this->getSite();
        $urltranslations = $site ? ($site->config['urltranslations'] ?? null) : null;
        if ($urltranslations === null) {
            $urltranslations = $this->settingsRepo ? $this->settingsRepo->getUrlTranslations() : Database::getUrlTranslations();
        }
        if (is_array($urltranslations)) {
            foreach ($urltranslations as $key => $val) {
                if (isset($val[$lang]) && stripos($val[$lang], $slug) !== false) {
                    return $key;
                }
            }
        }
        return '';
    }

    /**
     * Check if a page or kind is eligible to be shown on home/recent listings.
     *
     * @param mixed $var
     * @return bool
     */
    public function isListingEligible(mixed $var): bool
    {
        $kind = $var instanceof Page ? $var->kind : ($var['kind'] ?? null);
        if ($kind !== null) {
            $config = $this->getKindConfig($kind);
            return !empty($config['show_on_home']);
        }
        return false;
    }
}
