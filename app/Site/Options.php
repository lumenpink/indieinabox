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
    /**
     * @var bool
     */
    public bool $dev = false;

    /**
     * @var bool
     */
    public bool $buildAll;

    /**
     * @var bool
     */
    public bool $skipStatic;
    /**
     * @var bool
     */
    public bool $forceStaticOverride;
    /**
     * @var bool
     */
    public bool $forceRebuild;
    /**
     * @var bool
     */
    public bool $skipMedia;
    /**
     * @var bool
     */
    public bool $skipPages;
    /**
     * @var ?string
     */
    public ?string $htmlpostprocessing;
    /**
     * @var bool
     */
    public bool $prettylinks;
    /**
     * @var int
     */
    public int $feed_limit;

    /**
     * @var string
     */
    public string $translation_parity;
    /**
     * @var string
     */
    public string $translation_auto;

    /**
     * SiteOptions constructor.
     *
     * @param bool $buildAll
     * @param bool $skipStatic
     * @param bool $forceStaticOverride
     * @param string|null $htmlpostprocessing
     * @param bool $prettylinks
     * @param int $feed_limit
     * @param string $translation_parity
     * @param string $translation_auto
     */
    public function __construct(
        bool $buildAll = true,
        bool $skipStatic = false,
        bool $forceStaticOverride = false,
        bool $forceRebuild = false,
        bool $skipMedia = false,
        bool $skipPages = false,
        ?string $htmlpostprocessing = null,
        bool $prettylinks = true,
        int $feed_limit = 20,
        string $translation_parity = 'full',
        string $translation_auto = 'pseudo'
    ) {
        $this->buildAll = $buildAll;
        $this->skipStatic = $skipStatic;
        $this->forceStaticOverride = $forceStaticOverride;
        $this->forceRebuild = $forceRebuild;
        $this->skipMedia = $skipMedia;
        $this->skipPages = $skipPages;
        $this->htmlpostprocessing = $htmlpostprocessing;
        $this->prettylinks = $prettylinks;
        $this->feed_limit = $feed_limit;
        $this->translation_parity = $translation_parity;
        $this->translation_auto = $translation_auto;
    }
}
