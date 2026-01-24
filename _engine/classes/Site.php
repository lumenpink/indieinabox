<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Site\Metadata;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Options;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Support;

class Site
{
    public Metadata $metadata;
    public Paths $paths;
    public Options $options;
    public Localization $localization;
    public Support $support;
    /**
     * Config constructor.
     *
     * @param Metadata $metadata
     * @param Paths $paths
     * @param Options $options
     * @param Localization $localization
     * @param Support $support
     */
    public function __construct(
        Metadata $metadata = null,
        Paths $paths = null,
        Options $options = null,
        Localization $localization = null,
        Support $support = null
    ) {
        $this->metadata = $metadata ?? new Metadata();
        $this->paths = $paths ?? new Paths();
        $this->options = $options ?? new Options();
        $this->localization = $localization ?? new Localization();
        $this->support = $support ?? new Support();
    }
}
