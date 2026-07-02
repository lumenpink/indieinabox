<?php

declare(strict_types=1);

namespace Indieinabox\Twtxt;

use DateTime;

/**
 * Class TwtxtEntry
 */
class TwtxtEntry
{
    /**
     * @var DateTime
     */
    public DateTime $timestamp;
    /**
     * @var string
     */
    public string $nick;
    /**
     * @var string
     */
    public string $message;
    /**
     * @var string
     */
    public string $html;

    /**
     * @param DateTime $timestamp
     * @param string $nick
     * @param string $message
     * @param string $html
     */
    public function __construct(
        DateTime $timestamp,
        string $nick,
        string $message,
        string $html = ''
    ) {
        $this->timestamp = $timestamp;
        $this->nick = $nick;
        $this->message = $message;
        $this->html = $html === '' ? $message : $html;
    }
}
