<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

use Indieinabox\Page\Page;

/**
 * Class ParserInterface
 */
interface ParserInterface
{
    /**
     * @param  string $file
     * @return Page|false|null
     */
    public function parse(string $file);
}
