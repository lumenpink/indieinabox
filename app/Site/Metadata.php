<?php

declare(strict_types=1);

namespace Indieinabox\Site;

/**
 * Class Metadata
 *
 * Holds metadata related to the site.
 */
class Metadata
{
    /**
     * @var string
     */
    public string $sitename;
    /**
     * @var string
     */
    public string $author;
    /**
     * @var string
     */
    public string $defaultTitle;
    /**
     * @var string
     */
    public string $fqdn;
    /**
     * @var ?string
     */
    public ?string $indieauthPassword = null;

    /**
     * @var string
     */
    public string $description;

    /**
     * SiteMetadata constructor.
     * @param string $sitename
     * @param string $author
     * @param string $defaultTitle
     * @param string $fqdn
     * @param string $description
     */
    public function __construct(
        string $sitename = "My Site",
        string $author = "Me",
        string $defaultTitle = "Untitled",
        string $fqdn = "http://localhost:8080",
        string $description = "My Site Description"
    ) {
        $this->sitename = $sitename;
        $this->author = $author;
        $this->defaultTitle = $defaultTitle;
        $this->fqdn = $fqdn;
        $this->description = $description;
    }
}
