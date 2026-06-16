<?php

declare(strict_types=1);

/**
 * @param \Indieinabox\Page|array<string, mixed> $page
 * @return array{localized: string, kind: string}
 */
function kind($page): array
{
    return \Indieinabox\Helper::kind($page);
}
