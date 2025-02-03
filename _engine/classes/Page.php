<?php

namespace Indieinabox;

class Page
{
    /**
     * @var array
     */
    public $category;

    /**
     * @var string
     */
    public $content;

    /**
     * @var DateTime
     */
    public $date;

    /**
     * @var array
     */
    public $images;

    /**
     * @var string
     */
    public $isodate;

    /**
     * @var string
     */
    public $kind;

    /**
     * @var string
     */
    public $lang;

    /**
     * @var string
     */
    public $langpath;

    /**
     * @var string
     */
    public $langslug;

    /**
     * @var string
     */
    public $layout;

    /**
     * @var string
     */
    public $localizeddate;

    /**
     * @var string
     */
    public $localizedkind;

    /**
     * @var string
     */
    public $nick;

    /**
     * @var bool
     */
    public $noauthor;

    /**
     * @var array
     */
    public $otherlang;

    /**
     * @var array
     */
    public $otherlangpath;

    /**
     * @var string
     */
    public $originalcontent;

    /**
     * @var string
     */
    public $relpath;

    /**
     * @var string
     */
    public $slug;

    /**
     * @var array
     */
    public $tags;

    /**
     * @var string
     */
    public $title;

    public function __construct(
        array $category = ["No Category"],
        string $content = "Dummy content",
        ?\DateTime $date = null,
        array $images = [],
        string $isodate = "2001-01-01T00:00Z",
        string $kind = "note",
        string $lang = "en",
        string $langpath = "",
        string $langslug = "untitled",
        string $layout = "page",
        string $localizeddate = "Saturday, January 1 of 2001, 00:00 UTC",
        string $localizedkind = "note",
        string $nick = "untitled",
        bool $noauthor = false,
        array $otherlang = [],
        array $otherlangpath = [],
        string $originalcontent = "untitled",
        string $relpath = "",
        string $slug = "untitled",
        array $tags = ["No Tag"],
        string $title = "Untitled"
    ) {
        $this->category = $category;
        $this->content = $content;
        $this->date = $date ?? new DateTime('now');
        $this->images = $images;
        $this->isodate = $isodate;
        $this->kind = $kind;
        $this->lang = $lang;
        $this->langpath = $langpath;
        $this->langslug = $langslug;
        $this->layout = $layout;
        $this->localizeddate = $localizeddate;
        $this->localizedkind = $localizedkind;
        $this->nick = $nick;
        $this->noauthor = $noauthor;
        $this->otherlang = $otherlang;
        $this->otherlangpath = $otherlangpath;
        $this->originalcontent = $originalcontent;
        $this->relpath = $relpath;
        $this->slug = $slug;
        $this->tags = $tags;
        $this->title = $title;
    }
}

class Pages extends ArrayObject
{
    /**
     * @var array
     */
    public $pages;

    public function __construct(array $pages = [])
    {
        parent::__construct();
        $this->pages = $pages;
    }

    public function add(Page $page, $id = null)
    {
        if ($id === null) {
            $this->pages[] = $page;
        } else {
            $this->pages[$id] = $page;
        }
    }

    public function getAll(): array
    {
        return $this->pages;
    }

    public function get(int $id): ?Page
    {
        return $this->pages[$id] ?? null;
    }
}
