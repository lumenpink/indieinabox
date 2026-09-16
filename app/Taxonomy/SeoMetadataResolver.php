<?php

declare(strict_types=1);

namespace Indieinabox\Taxonomy;

use Indieinabox\Core\Container;
use Indieinabox\Page\Page;
use Indieinabox\Site\Site;
use Indieinabox\Taxonomy\Contracts\SeoMetadataResolverInterface;

/**
 * Class SeoMetadataResolver
 *
 * Domain service responsible for extracting, resolving, and formatting SEO descriptions,
 * fallback images, absolute URLs, and Schema.org structured data types for pages.
 */
class SeoMetadataResolver implements SeoMetadataResolverInterface
{
    private ?Site $site;

    public function __construct(?Site $site = null)
    {
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $this->site = $site ?? ($container && $container->has(Site::class)
            ? $container->get(Site::class)
            : ($GLOBALS['site'] ?? null));
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
     * Extract and normalize SEO metadata for a page.
     *
     * @param Page $page
     * @return array{description: string, image: string, image_alt: string, schema_type: string}
     */
    public function resolve(Page $page): array
    {
        // 1. Description Fallback (always truncated to 150 chars max for safety)
        $description = $page->metadata->description ?? '';
        if (empty($description)) {
            $description = strip_tags((string) ($page->content ?? ''));
        }

        // Remove newlines and multiple spaces
        $description = (string) preg_replace('/\s+/', ' ', $description);
        $description = trim($description);

        // Truncate safely (150 chars max)
        if (mb_strlen($description) > 150) {
            $description = mb_substr($description, 0, 147) . '...';
        }

        // 2. Image Fallback
        $image = $page->metadata->image ?? '';
        $imageAlt = $page->metadata->image_alt ?? '';

        if (empty($image) && !empty($page->content)) {
            $contentStr = (string) $page->content;
            if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $contentStr, $matches)) {
                $image = $matches[1];
                if (preg_match('/alt=[\'"]([^\'"]*)[\'"]/i', $matches[0], $altMatches)) {
                    $imageAlt = $altMatches[1];
                }
            }
        }

        $site = $this->getSite();

        // Default hardcoded image if still empty
        if (empty($image)) {
            $baseUrl = rtrim($site?->metadata->fqdn ?? '', '/');
            $image = $baseUrl . '/media/default.png';
            $imageAlt = $site?->metadata->sitename ?? 'Default site image';
        } else {
            // Ensure image URL is absolute
            if (!preg_match('/^https?:\/\//i', $image)) {
                $baseUrl = rtrim($site?->metadata->fqdn ?? '', '/');
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
            'schema_type' => $schemaType,
        ];
    }
}
