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
     * @var string|null
     */
    public $rawBody;

    /**
     * PageContent constructor.
     *
     * @param string $content
     * @param string $originalcontent
     * @param array<string> $images
     * @param string|null $rawBody
     */
    public function __construct(
        string $content = "Hello World",
        string $originalcontent = "Hello World",
        array $images = [],
        ?string $rawBody = null
    ) {
        $this->content = $content;
        $this->originalcontent = $originalcontent;
        $this->images = $images;
        $this->rawBody = $rawBody;
    }

    /**
     * Convert Content object to its string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->content;
    }
}
