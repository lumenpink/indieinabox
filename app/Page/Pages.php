<?php

/**
 * Class Pages
 *
 * This class represents a collection of pages.
 */

declare(strict_types=1);

namespace Indieinabox\Page;

use ArrayObject;
use Indieinabox\Specifications\Contracts\SpecificationInterface;

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

    /**
     * Retrieves recent posts, optionally filtered by language and custom predicate, sorted descending by date.
     *
     * @param int $limit Maximum number of posts to return.
     * @param string|null $lang Optional language to filter by.
     * @param callable|null $filter Optional filter predicate.
     * @return array<int, Page>
     */
    public function getRecentPosts(int $limit = 5, ?string $lang = null, ?callable $filter = null): array
    {
        $filtered = array_values($this->pages);
        if ($filter !== null) {
            $filtered = array_filter($filtered, $filter);
        }
        if ($lang !== null) {
            $filtered = array_filter($filtered, function ($p) use ($lang) {
                $pageLang = $p instanceof Page ? $p->lang : ($p['lang'] ?? 'en');
                return $pageLang === $lang;
            });
        }
        usort($filtered, function ($a, $b) {
            $dateA = $a instanceof Page ? $a->date : ($a['date'] ?? 0);
            $dateB = $b instanceof Page ? $b->date : ($b['date'] ?? 0);
            $timeA = $dateA instanceof \DateTimeInterface ? $dateA->getTimestamp() : (int) $dateA;
            $timeB = $dateB instanceof \DateTimeInterface ? $dateB->getTimestamp() : (int) $dateB;
            return $timeB <=> $timeA;
        });

        return array_slice(array_values($filtered), 0, $limit);
    }

    /**
     * Queries pages matching a given specification.
     *
     * @param SpecificationInterface $specification
     * @return array<string, Page|array<string, mixed>>
     */
    public function query(SpecificationInterface $specification): array
    {
        return array_filter($this->pages, function ($p) use ($specification) {
            $candidate = ($p instanceof Page) ? $p->toArray() : (array) $p;
            return $specification->isSatisfiedBy($candidate);
        });
    }
}
