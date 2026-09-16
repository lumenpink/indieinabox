<?php

declare(strict_types=1);

namespace Indieinabox\Commands;

use Indieinabox\Site\Site;

/**
 * Command to update properties of an existing post.
 */
final class UpdatePostCommand
{
    private Site $site;
    private string $url;
    /** @var array<string, mixed> */
    private array $replace;
    /** @var array<string, mixed> */
    private array $add;
    /** @var array<int, string> */
    private array $delete;

    /**
     * @param Site $site
     * @param string $url Canonical URL or relative path of the post
     * @param array<string, mixed> $replace Properties to replace
     * @param array<string, mixed> $add Properties to append
     * @param array<int, string> $delete Property keys to remove
     */
    public function __construct(
        Site $site,
        string $url,
        array $replace = [],
        array $add = [],
        array $delete = []
    ) {
        $this->site = $site;
        $this->url = $url;
        $this->replace = $replace;
        $this->add = $add;
        $this->delete = $delete;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return array<string, mixed>
     */
    public function getReplace(): array
    {
        return $this->replace;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdd(): array
    {
        return $this->add;
    }

    /**
     * @return array<int, string>
     */
    public function getDelete(): array
    {
        return $this->delete;
    }
}
