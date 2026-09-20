<?php

declare(strict_types=1);

namespace Indieinabox\Taxonomy;

use Indieinabox\Core\Container;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Repositories\Contracts\InteractionRepositoryInterface;
use Indieinabox\Repositories\FileInteractionRepository;
use Indieinabox\Site\Site;
use Indieinabox\Taxonomy\Contracts\SeoMetadataResolverInterface;
use Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface;
use Indieinabox\Theme\ThemeManager;

/**
 * Class KindHelper
 *
 * Facade bridge delegating to TaxonomyService, SeoMetadataResolver,
 * and Pages collection methods.
 */
class KindHelper
{
    private static function getTaxonomyService(?Site $site = null): TaxonomyServiceInterface
    {
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        if ($container && $container->has(TaxonomyServiceInterface::class)) {
            $service = $container->get(TaxonomyServiceInterface::class);
            if ($site !== null && $service instanceof TaxonomyService) {
                return new TaxonomyService($site);
            }
            return $service;
        }
        return new TaxonomyService($site);
    }

    private static function getSeoResolver(?Site $site = null): SeoMetadataResolverInterface
    {
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        if ($container && $container->has(SeoMetadataResolverInterface::class)) {
            $service = $container->get(SeoMetadataResolverInterface::class);
            if ($site !== null && $service instanceof SeoMetadataResolver) {
                return new SeoMetadataResolver($site);
            }
            return $service;
        }
        return new SeoMetadataResolver($site);
    }

    /**
     * Retrieves kind configuration with sensible defaults.
     *
     * @param string $kind
     * @return array<string, mixed>
     */
    public static function getKindConfig(string $kind): array
    {
        return self::getTaxonomyService()->getKindConfig($kind);
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
        return self::getTaxonomyService($siteInstance)->resolveKind($page);
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
        return self::getTaxonomyService()->getKindFolder($kind, $lang);
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
        return self::getTaxonomyService()->getKindLabel($kind, $lang);
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
        return self::getTaxonomyService()->getKindLink($page, $kind);
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
        return self::getTaxonomyService()->getOriginalContent($slug, $lang);
    }

    /**
     * List posts, sorting by date descending, up to 5 posts.
     * Delegates sorting to Pages::getRecentPosts.
     *
     * @param Pages|null $pageCollection
     * @param Site|null $siteInstance
     * @param Page|null $currentPage
     * @return string
     */
    public static function listposts(?Pages $pageCollection = null, ?Site $siteInstance = null, ?Page $currentPage = null): string
    {
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $site = $siteInstance ?? ($container && $container->has(Site::class) ? $container->get(Site::class) : ($GLOBALS['site'] ?? null));
        $pages = $pageCollection ?? ($container && $container->has(Pages::class) ? $container->get(Pages::class) : null);
        $mainPage = $currentPage ?? ($container && $container->has(Page::class) ? $container->get(Page::class) : null);
        $currentLang = $mainPage instanceof Page
            ? $mainPage->lang
            : ($mainPage['lang'] ?? ($site?->localization->defaultLang ?? 'en'));
        $base = $site?->paths->baseDir ?? '';

        if (!($pages instanceof Pages)) {
            $pages = new Pages(is_array($pages) ? $pages : []);
        }

        $taxonomyService = self::getTaxonomyService($site);
        $recentPages = $pages->getRecentPosts(5, $currentLang, [$taxonomyService, 'isListingEligible']);

        $count = 0;
        ob_start();
        $themeDir = $site?->paths->themeDir ?? 'theme';
        foreach ($recentPages as $originalPage) {
            $page = clone $originalPage;
            if ($mainPage instanceof Page) {
                $page->relpath = $mainPage->relpath;
            }
            if ($count > 0) {
                echo "<hr class=\"divisor-bloco\">\n";
            }
            ThemeManager::loadView(
                $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . 'views/includes/summary.php',
                get_defined_vars()
            );
            $count++;
        }
        return (string) ob_get_clean();
    }

    /**
     * Remove generic/page items from filter list.
     *
     * @param mixed $var
     * @return bool
     */
    public static function removeGeneric(mixed $var): bool
    {
        return self::getTaxonomyService()->isListingEligible($var);
    }

    /**
     * Helper function to extract and normalize SEO metadata.
     *
     * @param Page $page
     * @return array<string, string>
     */
    public static function getSeoMetadata(Page $page): array
    {
        return self::getSeoResolver()->resolve($page);
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
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        if ($container && $container->has(InteractionRepositoryInterface::class)) {
            try {
                $repo = $container->get(InteractionRepositoryInterface::class);
                return $repo->findByPageSlug($page->slug, $type);
            } catch (\Throwable) {
                // Fallback
            }
        }

        $repo = new FileInteractionRepository();
        return $repo->findByPageSlug($page->slug, $type);
    }
}
