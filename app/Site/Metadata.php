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
    public string $title;
    public string $sitename;
    public string $author;
    public string $defaultTitle;
    public string $fqdn;
    public ?string $indieauthPassword = null;

    /**
     * SiteMetadata constructor.
     *
     * @param string $title
     * @param string $sitename
     * @param string $author
     * @param string $defaultTitle
     * @param string $fqdn
     */
    public function __construct(
        string $title = "My Site",
        string $sitename = "My Site",
        string $author = "Me",
        string $defaultTitle = "Untitled",
        string $fqdn = "http://localhost:8080"
    ) {
        $this->title = $title;
        $this->sitename = $sitename;
        $this->author = $author;
        $this->defaultTitle = $defaultTitle;
        $this->fqdn = $fqdn;
    }
}
