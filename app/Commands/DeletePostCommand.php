<?php

declare(strict_types=1);

namespace Indieinabox\Commands;

use Indieinabox\Site\Site;

/**
 * Command to delete an existing post and broadcast federation deletion.
 */
final class DeletePostCommand
{
    private Site $site;
    private string $url;

    /**
     * @param Site $site
     * @param string $url Canonical URL or relative path of the post
     */
    public function __construct(Site $site, string $url)
    {
        $this->site = $site;
        $this->url = $url;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
