<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Site\Metadata;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Options;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Support;
use Indieinabox\Site\Twtxt;

class Site
{
    /** @var array<string, mixed> */
    public array $config = [];
    public Metadata $metadata;
    public Paths $paths;
    public Options $options;
    public Localization $localization;
    public Support $support;
    public Twtxt $twtxt;

    /**
     * Config constructor.
     *
     * @param Metadata $metadata
     * @param Paths $paths
     * @param Options $options
     * @param Localization $localization
     * @param Support $support
     * @param Twtxt $twtxt
     */
    public function __construct(
        ?Metadata $metadata = null,
        ?Paths $paths = null,
        ?Options $options = null,
        ?Localization $localization = null,
        ?Support $support = null,
        ?Twtxt $twtxt = null
    ) {
        $this->metadata = $metadata ?? new Metadata();
        $this->paths = $paths ?? new Paths();
        $this->options = $options ?? new Options();
        $this->localization = $localization ?? new Localization();
        $this->support = $support ?? new Support();
        $this->twtxt = $twtxt ?? new Twtxt();
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        switch (strtolower($name)) {
            case 'dev':
                return $this->options->dev;
            case 'buildall':
                return $this->options->buildAll;
            case 'forcestaticoverride':
                return $this->options->forceStaticOverride;
            case 'htmlpostprocessing':
                return $this->options->htmlpostprocessing;
            case 'prettylinks':
                return $this->options->prettylinks;
            case 'output':
                return rtrim($this->paths->baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->paths->outputDirHtml;
            case 'outputdir':
                return $this->paths->outputDirHtml;
            case 'contentdir':
                return $this->paths->contentDir;
            case 'defaultlang':
                return $this->localization->defaultLang;
            case 'lang':
                return $this->localization->lang;
            case 'defaulttitle':
                return $this->metadata->defaultTitle;
        }
        return null;
    }
}
