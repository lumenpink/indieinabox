<?php

declare(strict_types=1);

namespace Indieinabox\Taxonomy;

use Indieinabox\Core\Database;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Site;
use Indieinabox\Support\TextParser;
use Indieinabox\Theme\ThemeManager;
use Indieinabox\Support\Yaml;

/**
 * Class KindHelper
 *
 * Manages post kind taxonomy, kind configuration, URL mapping,
 * post listing, SEO metadata resolution, and incoming interactions.
 */
class KindHelper
{
    private static function getSite(): ?Site
    {
        $container = \Indieinabox\Core\Container::getInstance();
        return $container->has(Site::class) ? $container->get(Site::class) : ($GLOBALS['site'] ?? null);
    }

    /**
     * Retrieves kind configuration with sensible defaults.
     *
     * @param string $kind
     * @return array<string, mixed>
     */
    public static function getKindConfig(string $kind): array
    {
        $site = self::getSite();
        $kind = strtolower($kind);

        if ($site && !empty($site->config['kinds'])) {
            $config = $site->config['kinds'][$kind] ?? [];
        } else {
            $kinds = Database::getKinds();
            if ($site) {
                $site->config['kinds'] = $kinds;
            }
            $config = $kinds[$kind] ?? [];
        }

        if (empty($config['content_dir'])) {
            $kindsPath = $site ? ($site->config['kindspath'] ?? null) : null;
            if ($kindsPath === null || empty($kindsPath)) {
                $kindsPath = !empty($GLOBALS['kindspath']) ? $GLOBALS['kindspath'] : Database::getSetting('kindspath', []);
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
            'display_mode' => 'default'
        ], $config);
    }

    /**
     * Determines the kind and localized folder for a page.
     *
     * @param Page|array<string, mixed> $page
     * @param Site|null $siteInstance
     * @return array{localized: string, kind: string}
     */
    public static function kind(mixed $page, ?Site $siteInstance = null): array
    {
        $site = $siteInstance ?? self::getSite();
        $isObject = $page instanceof Page;
        $pageKind = $isObject ? $page->kind : ($page["kind"] ?? null);
        $pageSlug = $isObject ? $page->slug : ($page["slug"] ?? "");
        $pageLang = $isObject ? $page->lang : ($page["lang"] ?? "en");

        if ($pageKind !== null && $pageKind !== "") {
            $kind = $pageKind;
            $kindConfig = $site->config['kinds'][$kind] ?? null;
            if ($kindConfig) {
                $localizedkind = self::getKindFolder($kind, $pageLang);
            } else {
                $localizedkind = $kind;
            }
        } else {
            $localizedkindSegment = explode("/", $pageSlug);
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
                    $kindsPath = !empty($GLOBALS['kindspath']) ? $GLOBALS['kindspath'] : Database::getSetting('kindspath', []);
                }
                if (!empty($kindsPath)) {
                    foreach ($kindsPath as $key => $value) {
                        if (in_array($localizedkindSegment, (array)$value, true)) {
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
                    $relPath = str_replace($contentPath, "", $page->filepath ?? '');
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
                $kind = $isRoot ? "page" : "generic";
                $localizedkind = "generic";
            } else {
                $kindConfig = $site->config['kinds'][$kind] ?? null;
                if ($kindConfig) {
                    $localizedkind = self::getKindFolder($kind, $pageLang);
                } else {
                    $localizedkind = $localizedkindSegment;
                }
            }
        }
        return [
            "localized" => $localizedkind,
            "kind" => $kind,
        ];
    }

    /**
     * Get the localized folder name for a specific kind and language.
     *
     * @param string $kind
     * @param string $lang
     * @return string
     */
    public static function getKindFolder(string $kind, string $lang): string
    {
        $config = self::getKindConfig($kind);

        // If it's a special system kind (generic, page, home) and has no config, just return the kind itself
        if (in_array($kind, ['generic', 'page', 'home'], true) && empty($config['title'])) {
            return $kind;
        }

        // Use content_dir if defined
        if (isset($config['content_dir'])) {
            if (is_array($config['content_dir'])) {
                $folder = $config['content_dir'][$lang] ?? reset($config['content_dir']);
                if ($folder) {
                    return TextParser::slugize((string)$folder);
                }
            } else {
                return TextParser::slugize((string)$config['content_dir']);
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
    public static function kindLabel(string $kind, ?string $lang = null): string
    {
        $site = self::getSite();
        $config = self::getKindConfig($kind);
        $targetLang = $lang ?? $site->localization->defaultLang ?? 'en';

        if (!empty($config['title']) && is_array($config['title'])) {
            if (isset($config['title'][$targetLang])) {
                return $config['title'][$targetLang];
            }
            if (isset($config['title']['en'])) {
                return $config['title']['en'];
            }
            return (string)reset($config['title']);
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
    public static function kindLink(Page $page, string $kind): string
    {
        $site = self::getSite();
        $lang = $page->lang ?? $site->localization->defaultLang ?? 'en';
        $defaultLang = $site->localization->defaultLang ?? 'en';
        $prettylinks = $site->options->prettylinks ?? true;

        $label = self::kindLabel($kind, $lang);

        // If it's a generic kind or hidden from menu, don't link it
        $isHidden = false;
        if (isset($site->config['kinds'][$kind]['show_in_menu']) && !$site->config['kinds'][$kind]['show_in_menu']) {
            $isHidden = true;
        }

        if ($isHidden || in_array($kind, ['generic', 'home', 'page'], true)) {
            return '[' . strtoupper(htmlspecialchars($label)) . ']';
        }

        $folder = self::getKindFolder($kind, $lang);
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
    public static function getOriginalContent(string $slug, string $lang): string
    {
        $site = self::getSite();
        $urltranslations = $site ? ($site->config['urltranslations'] ?? null) : null;
        if ($urltranslations === null) {
            $urltranslations = Database::getUrlTranslations();
        }
        if (is_array($urltranslations)) {
            foreach ($urltranslations as $key => $val) {
                if (isset($val[$lang]) && stripos($val[$lang], $slug) !== false) {
                    return $key;
                }
            }
        }
        return "";
    }

    /**
     * List posts, sorting by date descending, up to 10 posts.
     *
     * @param Pages|null $pageCollection
     * @param Site|null $siteInstance
     * @param Page|null $currentPage
     * @return string
     */
    public static function listposts(?Pages $pageCollection = null, ?Site $siteInstance = null, ?Page $currentPage = null): string
    {
        $container = \Indieinabox\Core\Container::getInstance();
        $site = $siteInstance ?? self::getSite();
        $pages = $pageCollection ?? ($container->has(Pages::class) ? $container->get(Pages::class) : null);
        $mainPage = $currentPage ?? ($container->has(Page::class) ? $container->get(Page::class) : null);
        $currentLang = $mainPage instanceof Page
            ? $mainPage->lang
            : ($mainPage['lang'] ?? ($site?->localization->defaultLang ?? 'en'));
        $base = $site?->paths->baseDir ?? '';
        $localpages = $pages instanceof Pages ? $pages->all() : (is_array($pages) ? $pages : []);
        $localpages = array_filter($localpages, [self::class, 'removeGeneric']);
        $localpages = array_filter($localpages, function ($pg) use ($currentLang) {
            $lang = $pg instanceof Page ? $pg->lang : ($pg['lang'] ?? 'en');
            return $lang === $currentLang;
        });
        usort(
            $localpages,
            function ($a, $b) {
                $dateA = $a instanceof Page ? $a->date : ($a['date'] ?? 0);
                $dateB = $b instanceof Page ? $b->date : ($b['date'] ?? 0);
                $timeA = $dateA instanceof \DateTime ? $dateA->getTimestamp() : $dateA;
                $timeB = $dateB instanceof \DateTime ? $dateB->getTimestamp() : $dateB;
                return $timeB <=> $timeA;
            }
        );
        $count = 0;
        ob_start();
        $themeDir = $site?->paths->themeDir ?? 'theme';
        foreach ($localpages as $originalPage) {
            $p = clone $originalPage;
            $page = clone $originalPage;
            if ($mainPage instanceof Page) {
                $page->relpath = $mainPage->relpath;
            }
            if ($count > 0) {
                echo "<hr class=\"divisor-bloco\">\n";
            }
            ThemeManager::loadView(
                $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views/includes/summary.php",
                get_defined_vars()
            );
            $count++;
            if ($count >= 5) {
                break;
            }
        }
        return (string)ob_get_clean();
    }

    /**
     * Remove generic/page items from filter list.
     *
     * @param mixed $var
     * @return bool
     */
    public static function removeGeneric(mixed $var): bool
    {
        $kind = $var instanceof Page ? $var->kind : ($var["kind"] ?? null);
        if ($kind !== null) {
            $config = self::getKindConfig($kind);
            return !empty($config['show_on_home']);
        }
    }

    /**
     * Helper function to extract and normalize SEO metadata.
     *
     * @param Page $page
     * @return array<string, string>
     */
    public static function getSeoMetadata(Page $page): array
    {
        // 1. Description Fallback (always truncated to 150 chars max for safety)
        $description = $page->metadata->description ?? '';
        if (empty($description)) {
            $description = strip_tags((string)($page->content ?? ''));
        }

        // Remove newlines and multiple spaces
        $description = (string)preg_replace('/\s+/', ' ', $description);
        $description = trim($description);

        // Truncate safely (150 chars max)
        if (mb_strlen($description) > 150) {
            $description = mb_substr($description, 0, 147) . '...';
        }

        // 2. Image Fallback
        $image = $page->metadata->image ?? '';
        $imageAlt = $page->metadata->image_alt ?? '';

        if (empty($image) && !empty($page->content)) {
            $contentStr = (string)$page->content;
            if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $contentStr, $matches)) {
                $image = $matches[1];
                if (preg_match('/alt=[\'"]([^\'"]*)[\'"]/i', $matches[0], $altMatches)) {
                    $imageAlt = $altMatches[1];
                }
            }
        }

        // Default hardcoded image if still empty
        if (empty($image)) {
            $site = self::getSite();
            $baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
            $image = $baseUrl . '/media/default.png';
            $imageAlt = $site->metadata->sitename ?? 'Default site image';
        } else {
            // Ensure image URL is absolute
            if (!preg_match('/^https?:\/\//i', $image)) {
                $site = self::getSite();
                $baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
                if (str_starts_with($image, '/')) {
                    $image = $baseUrl . $image;
                } else {
                    $image = $baseUrl . '/' . ltrim($page->relpath ?? '', '/') . $image;
                }
            }
        }

        if (empty($imageAlt)) {
            $imageAlt = $page->title ?? 'Post thumbnail';
        }

        // 3. Schema.org Type Mapping
        $kind = strtolower($page->kind ?? 'generic');
        $schemaType = 'WebPage';
        switch ($kind) {
            case 'article':
                $schemaType = 'BlogPosting';
                break;
            case 'photo':
            case 'video':
            case 'audio':
                $schemaType = 'MediaObject';
                break;
            case 'jardim':
                $schemaType = 'Article';
                break;
            case 'note':
            case 'like':
            case 'repost':
            case 'bookmark':
            case 'read':
            case 'listen':
            case 'watch':
            case 'checkin':
                $schemaType = 'SocialMediaPosting';
                break;
            case 'reply':
            case 'rsvp':
                $schemaType = 'Comment';
                break;
        }

        return [
            'description' => $description,
            'image' => $image,
            'image_alt' => $imageAlt,
            'schema_type' => $schemaType
        ];
    }

    /**
     * Get incoming interactions (likes, reposts, replies) for a specific Page.
     *
     * @param Page $page
     * @param string|null $type (e.g. 'like', 'repost', 'reply')
     * @return array<int, mixed>
     */
    public static function getInteractions(Page $page, ?string $type = null): array
    {
        if (class_exists(\Indieinabox\Core\Container::class)) {
            try {
                $repo = \Indieinabox\Core\Container::getInstance()->make(\Indieinabox\Repositories\Contracts\InteractionRepositoryInterface::class);
                return $repo->findByPageSlug($page->slug, $type);
            } catch (\Throwable) {
                // Fallback
            }
        }

        $repo = new \Indieinabox\Repositories\FileInteractionRepository();
        return $repo->findByPageSlug($page->slug, $type);
    }
}
