<?php

declare(strict_types=1);

/**
 * @param \Indieinabox\Page|array<string, mixed> $page
 * @return array{long: string, iso: string}
 */
function localizeddate($page): array
{
    return \Indieinabox\Helper::localizeddate($page);
}
