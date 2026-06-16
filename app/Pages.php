<?php

/**
 * Class Pages
 *
 * This class represents a collection of pages.
 */

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Page;
use ArrayObject;

/**
 * @extends ArrayObject<string, Page>
 */
class Pages extends ArrayObject
{
    /**
     * @var array<string, Page>
     */
    public array $pages;

    /**
     * @param array<string, Page> $pages
     */
    public function __construct(array $pages = [])
    {
        parent::__construct();
        $this->pages = $pages;
    }

    /**
     * @param Page|array<string, mixed> $page
     * @param string|null $id
     */
    public function add($page, ?string $id = null): void
    {
        $slug = ($page instanceof Page) ? $page->slug : $page['slug'];
        if ($id === null) {
            $this->pages[$slug] = $page;
            $this->offsetSet($slug, $page);
        } else {
            $this->pages[(string) $id] = $page;
            $this->offsetSet((string) $id, $page);
        }
    }

    /**
     * @return array<string, Page>
     */
    public function all(): array
    {
        return $this->pages;
    }

    /**
     * @param string $id
     * @return Page|null
     */
    public function get(string $id): ?Page
    {
        return $this->pages[$id] ?? null;
    }
}
