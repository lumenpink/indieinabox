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
    /**
     * @var string
     */
    public string $baseDir;
    /**
     * @var string
     */
    public string $outputDirHtml;
    /**
     * @var string
     */
    public string $outputDirGemini;
    /**
     * @var string
     */
    public string $outputDirGopher;
    /**
     * @var string
     */
    public string $outputDirMedia;
    /**
     * @var string
     */
    public string $contentDir;
    /**
     * @var string
     */
    public string $themeDir;

    /**
     * SitePaths constructor.
     *
     * @param string $baseDir
     * @param string $outputDirHtml
     * @param string $outputDirGemini
     * @param string $outputDirGopher
     * @param string $outputDirMedia
     * @param string $contentDir
     * @param string $themeDir
     */
    public function __construct(
        string $baseDir = "/",
        string $outputDirHtml = "public_html",
        string $outputDirGemini = "public_gemini",
        string $outputDirGopher = "public_gopher",
        string $outputDirMedia = "public_html/media",
        string $contentDir = "content",
        string $themeDir = "resources"
    ) {
        $this->baseDir = $baseDir;
        $this->outputDirHtml = $outputDirHtml;
        $this->outputDirGemini = $outputDirGemini;
        $this->outputDirGopher = $outputDirGopher;
        $this->outputDirMedia = $outputDirMedia;
        $this->contentDir = $contentDir;
        $this->themeDir = $themeDir;
    }

    /**
     * Retrieves the absolute path to the content directory.
     * 
     * @return string
     */
    public function getContentPath(): string
    {
        // Check if contentDir is already an absolute path (e.g., /var/www/content or C:\content)
        if (str_starts_with($this->contentDir, DIRECTORY_SEPARATOR) || preg_match('#^[a-zA-Z]:\\\\#', $this->contentDir)) {
            return rtrim($this->contentDir, DIRECTORY_SEPARATOR);
        }
        
        return rtrim($this->baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($this->contentDir, DIRECTORY_SEPARATOR);
    }
}
