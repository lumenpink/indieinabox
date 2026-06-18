<?php

declare(strict_types=1);

namespace Indieinabox;

interface ParserInterface
{
    /**
     * @param  string $file
     * @return Page|false|null
     */
    public function parse(string $file);
}
