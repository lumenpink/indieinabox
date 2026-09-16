<?php

declare(strict_types=1);

namespace Indieinabox\Taxonomy\Contracts;

use Indieinabox\Page\Page;

/**
 * Interface SeoMetadataResolverInterface
 *
 * Defines contract for extracting and resolving SEO metadata, social share images,
 * and Schema.org structured types for pages.
 */
interface SeoMetadataResolverInterface
{
    /**
     * Extract and normalize SEO metadata for a page.
     *
     * @param Page $page
     * @return array{description: string, image: string, image_alt: string, schema_type: string}
     */
    public function resolve(Page $page): array;
}
