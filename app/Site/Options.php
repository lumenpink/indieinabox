<?php

declare(strict_types=1);

namespace Indieinabox\Site;

/**
 * Class Options
 *
 * Holds boolean flags and options for the site.
 */
class Options
{
    public bool $buildAll;
    public bool $dev;
    public bool $skipStatic;
    public bool $forceStaticOverride;
    public ?string $htmlpostprocessing;
    public bool $prettylinks;
    public int $feed_limit;

    /**
     * SiteOptions constructor.
     *
     * @param bool $buildAll
     * @param bool $dev
     * @param bool $skipStatic
     * @param bool $forceStaticOverride
     * @param string|null $htmlpostprocessing
     * @param bool $prettylinks
     */
    public function __construct(
        bool $buildAll = true,
        bool $dev = false,
        bool $skipStatic = false,
        bool $forceStaticOverride = false,
        ?string $htmlpostprocessing = null,
        bool $prettylinks = true,
        int $feed_limit = 20
    ) {
        $this->buildAll = $buildAll;
        $this->dev = $dev;
        $this->skipStatic = $skipStatic;
        $this->forceStaticOverride = $forceStaticOverride;
        $this->htmlpostprocessing = $htmlpostprocessing;
        $this->prettylinks = $prettylinks;
        $this->feed_limit = $feed_limit;
    }
}
