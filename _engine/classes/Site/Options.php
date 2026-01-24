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

    /**
     * SiteOptions constructor.
     *
     * @param bool $buildAll
     * @param bool $dev
     * @param bool $skipStatic
     * @param bool $forceStaticOverride
     */
    public function __construct(
        bool $buildAll = true,
        bool $dev = false,
        bool $skipStatic = false,
        bool $forceStaticOverride = false
    ) {
        $this->buildAll = $buildAll;
        $this->dev = $dev;
        $this->skipStatic = $skipStatic;
        $this->forceStaticOverride = $forceStaticOverride;
    }
}
