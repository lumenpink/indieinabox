<?php

declare(strict_types=1);

namespace Indieinabox\Page;

use DateTime;

/**
 * Class Metadata
 *
 * This class handles metadata related to the page.
 */
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
     * PageMetadata constructor.
     *
     * @param array<string> $category
     * @param array<string> $tags
     * @param string $title
     * @param string $nick
     * @param bool $noauthor
     * @param string $kind
     * @param string $layout
     */
    public function __construct(
        array $category = ["No Category"],
        array $tags = ["No Tag"],
        string $title = "Untitled",
        string $nick = "untitled",
        bool $noauthor = false,
        string $kind = "note",
        string $layout = "page"
    ) {
        $this->category = $category;
        $this->tags = $tags;
        $this->title = $title;
        $this->nick = $nick;
        $this->noauthor = $noauthor;
        $this->kind = $kind;
        $this->layout = $layout;
    }
}
