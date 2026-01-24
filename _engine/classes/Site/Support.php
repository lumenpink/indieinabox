<?php

declare(strict_types=1);

namespace Indieinabox\Site;

/**
 * Class Support
 *
 * Holds support-related configurations.
 */
class Support
{
    /** @var array<string> */
    public array $support;
    public string $defaultCategory;

    /**
     * SiteSupport constructor.
     *
     * @param array<string> $support
     * @param string $defaultCategory
     */
    public function __construct(
        array $support = ["md", "txt", "html", "htm"],
        string $defaultCategory = "General"
    ) {
        $this->support = $support;
        $this->defaultCategory = $defaultCategory;
    }
}
