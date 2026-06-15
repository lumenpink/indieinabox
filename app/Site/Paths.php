<?php

declare(strict_types=1);

namespace Indieinabox\Site;

/**
 * Class Paths
 *
 * Holds directory paths related to the site.
 */
class Paths
{
    public string $baseDir;
    public string $outputDir;
    public string $contentDir;

    /**
     * SitePaths constructor.
     *
     * @param string $baseDir
     * @param string $outputDir
     * @param string $contentDir
     */
    public function __construct(
        string $baseDir = "/",
        string $outputDir = "public",
        string $contentDir = "content"
    ) {
        $this->baseDir = $baseDir;
        $this->outputDir = $outputDir;
        $this->contentDir = $contentDir;
    }
}
