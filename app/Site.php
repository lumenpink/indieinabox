<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Site\Metadata;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Options;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Support;
use Indieinabox\Site\Twtxt;

/**
 * Class Site
 * 
 * Represents the global site configuration and state for the Indieinabox application.
 * It acts as a central registry for all configuration components such as metadata,
 * paths, localization, and feature flags, providing a unified interface to access them.
 */
class Site
{
    /**
     * @var array<string, mixed> The raw configuration array loaded from the database or config file.
     */
    public array $config = [];
    /**
     * @var Metadata Contains metadata about the site (e.g., title, author, description).
     */
    public Metadata $metadata;
    /**
     * @var Paths Stores all relevant directory and file paths used during site generation.
     */
    public Paths $paths;
    /**
     * @var Options Contains boolean flags and global options (e.g., dev mode, pretty links).
     */
    public Options $options;
    /**
     * @var Localization Handles language settings and translations for the site.
     */
    public Localization $localization;
    /**
     * @var Support Contains feature support settings (e.g., specific protocol or format toggles).
     */
    public Support $support;
    /**
     * @var Twtxt Contains Twtxt specific configurations, such as nickname and following list.
     */
    public Twtxt $twtxt;

    /**
     * Site constructor.
     *
     * Initializes a new Site configuration object. If any component is not provided,
     * it instantiates a default version of that component.
     *
     * @param Metadata|null $metadata Optional metadata configuration.
     * @param Paths|null $paths Optional paths configuration.
     * @param Options|null $options Optional global options configuration.
     * @param Localization|null $localization Optional localization settings.
     * @param Support|null $support Optional support features configuration.
     * @param Twtxt|null $twtxt Optional Twtxt configuration.
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
     * Magic getter for backward compatibility and quick access to deeply nested properties.
     *
     * @param string $name The name of the property to retrieve.
     * @return mixed The corresponding configuration value or null if not found.
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
