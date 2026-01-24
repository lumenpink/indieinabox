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
     * @param Page $page
     * @param string|null $id
     */
    public function add(Page $page, string $id = null): void
    {
        if ($id === null) {
            $this->pages[$page->slug] = $page;
        } else {
            // id must be a string
            $this->pages[(string) $id] = $page;
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
