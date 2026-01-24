<?php

declare(strict_types=1);

namespace Indieinabox\Page;

/**
 * Class Localization
 *
 * This class handles localization and language-related properties.
 */
class Localization
{
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
     * @var array<string>
     */
    public $otherlang;

    /**
     * @var array<string>
     */
    public $otherlangpath;

    /**
     * @var string
     */
    public $localizeddate;

    /**
     * @var string
     */
    public $localizedkind;

    /**
     * PageLocalization constructor.
     *
     * @param string $lang
     * @param string $langpath
     * @param string $langslug
     * @param array<string> $otherlang
     * @param array<string> $otherlangpath
     * @param string $localizeddate
     * @param string $localizedkind
     */
    public function __construct(
        string $lang = "en",
        string $langpath = "",
        string $langslug = "untitled",
        array $otherlang = [],
        array $otherlangpath = [],
        string $localizeddate = "Saturday, January 1 of 2001, 00:00 UTC",
        string $localizedkind = "note"
    ) {
        $this->lang = $lang;
        $this->langpath = $langpath;
        $this->langslug = $langslug;
        $this->otherlang = $otherlang;
        $this->otherlangpath = $otherlangpath;
        $this->localizeddate = $localizeddate;
        $this->localizedkind = $localizedkind;
    }
}
