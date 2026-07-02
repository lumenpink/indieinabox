<?php

declare(strict_types=1);

namespace Indieinabox\Site;

/**
 * Class Twtxt
 */
class Twtxt
{
    /**
     * @var string
     */
    public string $nick;
    /**
     * @var string
     */
    public string $description;
    /**
     * @var string
     */
    public string $avatar;
    /**
     * @var array<array{nick: string, url: string}>
     */
    public array $following;
    /**
     * @var array<string>
     */
    public array $hubs;

    /**
     * @param string $nick
     * @param string $description
     * @param string $avatar
     * @param array<array{nick: string, url: string}> $following
     * @param array<string> $hubs
     */
    public function __construct(
        string $nick = '',
        string $description = '',
        string $avatar = '',
        array $following = [],
        array $hubs = []
    ) {
        $this->nick = $nick;
        $this->description = $description;
        $this->avatar = $avatar;
        $this->following = $following;
        $this->hubs = $hubs;
    }
}
