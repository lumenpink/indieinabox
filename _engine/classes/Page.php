<?php

declare(strict_types=1);

namespace Indieinabox;

use DateTime;
use Indieinabox\Page\Metadata;
use Indieinabox\Page\Content;
use Indieinabox\Page\Localization;

/**
 * Class Page
 *
 * This class represents a page and composes metadata, content, and localization.
 */
class Page
{
    /**
     * @var Metadata
     */
    public $metadata;

    /**
     * @var Content
     */
    public $content;

    /**
     * @var Localization
     */
    public $localization;

    /**
     * @var DateTime
     */
    public $date;

    /**
     * @var string
     */
    public $relpath;

    /**
     * @var string
     */
    public $slug;

    /**
     * Page constructor.
     *
     * @param Metadata $metadata
     * @param Content $content
     * @param Localization $localization
     * @param DateTime|null $date
     * @param string $relpath
     * @param string $slug
     */
    public function __construct(
        ?Metadata $metadata,
        ?Content $content,
        ?Localization $localization,
        ?DateTime $date = null,
        string $relpath = "",
        string $slug = "untitled"
    ) {
        $this->metadata = $metadata ?? new Metadata();
        $this->content = $content ?? new Content();
        $this->localization = $localization ?? new Localization();
        $this->date = $date ?? new DateTime('now');
        $this->relpath = $relpath;
        $this->slug = $slug;
    }
}
