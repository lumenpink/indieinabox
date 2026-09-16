<?php

/**
 * Class Pages
 *
 * This class represents a collection of pages.
 */

declare(strict_types=1);

namespace Indieinabox\Page;

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

    /**
     * Finds a page by its slug.
     */
    public function find(string $slug): ?Page
    {
        return $this->pages[$slug] ?? null;
    }

    /**
     * Checks if a page exists by slug.
     */
    public function has(string $slug): bool
    {
        return isset($this->pages[$slug]);
    }

    /**
     * Removes a page by slug.
     */
    public function remove(string $slug): void
    {
        unset($this->pages[$slug]);
        if ($this->offsetExists($slug)) {
            $this->offsetUnset($slug);
        }
    }

    /**
     * Filters pages by kind.
     *
     * @param string $kind
     * @return array<string, Page>
     */
    public function filterByKind(string $kind): array
    {
        return array_filter($this->pages, function ($p) use ($kind) {
            $pageKind = $p instanceof Page ? $p->kind : ($p['kind'] ?? null);
            return $pageKind === $kind;
        });
    }

    /**
     * Filters pages by language.
     *
     * @param string $lang
     * @return array<string, Page>
     */
    public function filterByLanguage(string $lang): array
    {
        return array_filter($this->pages, function ($p) use ($lang) {
            $pageLang = $p instanceof Page ? $p->lang : ($p['lang'] ?? null);
            return $pageLang === $lang;
        });
    }
}
