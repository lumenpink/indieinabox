<?php

declare(strict_types=1);

namespace Indieinabox\Twtxt;

use DateTime;

class TwtxtEntry
{
    public DateTime $timestamp;
    public string $nick;
    public string $message;
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
