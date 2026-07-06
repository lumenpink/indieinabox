<?php

declare(strict_types=1);

namespace Indieinabox\Page;

use DateTime;

/**
 * Class Metadata
 *
 * This class handles metadata related to the page.
 */
#[\AllowDynamicProperties]
class Metadata
{
    /**
     * @var array<string>
     */
    public $category;

    /**
     * @var array<string>
     */
    public $tags;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $nick;

    /**
     * @var bool
     */
    public $noauthor;

    /**
     * @var string
     */
    public $kind;

    /**
     * @var string
     */
    public $layout;

    /**
     * @var string|null
     */
    public $maturity;

    /**
     * @var string|null
     */
    public $reliability;

    /**
     * @var array<string>|null
     */
    public $flowerbed;

    /**
     * @var string|null
     */
    public $confidence;

    /**
     * @var string|null
     */
    public $importance;

    /**
     * @var string|bool|null
     */
    public $menu;

    /**
     * @var bool
     */
    public $hide_title;

    /**
     * @var bool|string
     */
    public $hide_on_rss;

    /**
     * @var int|null
     */
    public $menu_order;

    /**
     * @var bool|string|null
     */
    public $translated_by_ia;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var string|null
     */
    public $image;

    /**
     * @var string|null
     */
    public $image_alt;

    /**
     * PageMetadata constructor.
     *
     * @param array<string> $category
     * @param array<string> $tags
     * @param string $title
     * @param string $nick
     * @param bool $noauthor
     * @param string $kind
     * @param string $layout
     * @param string|null $maturity
     * @param string|null $reliability
     * @param bool|null $menu
     * @param int|null $menu_order
     */
    public function __construct(
        array $category = ["No Category"],
        array $tags = ["No Tag"],
        string $title = "Untitled",
        string $nick = "untitled",
        bool $noauthor = false,
        string $kind = "note",
        string $layout = "page",
        ?string $maturity = null,
        ?string $reliability = null,
        $menu = null,
        ?int $menu_order = null,
        bool $hide_title = false,
        $hide_on_rss = false,
        $translated_by_ia = null,
        ?string $description = null,
        ?string $image = null,
        ?string $image_alt = null
    ) {
        $this->category = $category;
        $this->tags = $tags;
        $this->title = $title;
        $this->nick = $nick;
        $this->noauthor = $noauthor;
        $this->kind = $kind;
        $this->layout = $layout;
        $this->maturity = $maturity;
        $this->reliability = $reliability;
        $this->menu = $menu;
        $this->menu_order = $menu_order;
        $this->hide_title = $hide_title;
        $this->hide_on_rss = $hide_on_rss;
        $this->translated_by_ia = $translated_by_ia;
        $this->description = $description;
        $this->image = $image;
        $this->image_alt = $image_alt;
    }
}
