<?php

declare(strict_types=1);

namespace Indieinabox\Commands;

use Indieinabox\Site\Site;

/**
 * Command to create and publish a new post.
 */
final class CreatePostCommand
{
    private Site $site;
    /** @var array<string, mixed> */
    private array $input;

    /**
     * @param Site $site
     * @param array<string, mixed> $input Form or JSON Micropub payload
     */
    public function __construct(Site $site, array $input)
    {
        $this->site = $site;
        $this->input = $input;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInput(): array
    {
        return $this->input;
    }
}
