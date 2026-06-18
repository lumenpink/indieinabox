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
    public string $themeDir;

    /**
     * SitePaths constructor.
     *
     * @param string $baseDir
     * @param string $outputDir
     * @param string $contentDir
     * @param string $themeDir
     */
    public function __construct(
        string $baseDir = "/",
        string $outputDir = "public",
        string $contentDir = "content",
        string $themeDir = "theme"
    ) {
        $this->baseDir = $baseDir;
        $this->outputDir = $outputDir;
        $this->contentDir = $contentDir;
        $this->themeDir = $themeDir;
    }
}
