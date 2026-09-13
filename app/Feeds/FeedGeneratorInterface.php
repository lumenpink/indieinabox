<?php

declare(strict_types=1);

namespace Indieinabox\Feeds;

use Indieinabox\Entry\Entry;
use Indieinabox\Site;

/**
 * Contract for format-specific feed generators.
 */
interface FeedGeneratorInterface
{
    /**
     * Generates a feed from a list of Entry objects and writes to the destination path.
     *
     * @param Entry[] $entries
     * @param string $outputPath
     * @param Site $site
     * @param string $lang
     */
    public function generate(array $entries, string $outputPath, Site $site, string $lang = 'en'): void;

    /**
     * Returns the default filename for this feed format (e.g. 'rss.xml', 'atom.xml', 'twtxt.txt').
     */
    public function getFilename(): string;
}
