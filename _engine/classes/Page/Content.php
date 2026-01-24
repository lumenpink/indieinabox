<?php

declare(strict_types=1);

namespace Indieinabox\Page;

/**
 * Class Content
 *
 * This class handles content-related properties of the page.
 */
class Content
{
    /**
     * @var string
     */
    public $content;

    /**
     * @var string
     */
    public $originalcontent;

    /**
     * @var array<string>
     */
    public $images;

    /**
     * PageContent constructor.
     *
     * @param string $content
     * @param string $originalcontent
     * @param array<string> $images
     */
    public function __construct(
        string $content = "Hello World",
        string $originalcontent = "Hello World",
        array $images = []
    ) {
        $this->content = $content;
        $this->originalcontent = $originalcontent;
        $this->images = $images;
    }
}
