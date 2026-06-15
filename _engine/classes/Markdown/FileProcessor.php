<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

class FileProcessor
{
    /**
     * @var object
     */
    private $site;

    /**
     * @var string
     */
    private $base;

    /**
     * @param object $site
     * @param string $base
     */
    public function __construct(object $site, string $base)
    {
        $this->site = $site;
        $this->base = $base;
    }

    /**
     * @param  string $file
     * @return bool
     */
    public function isValidFile(string $file): bool
    {
        if (!file_exists($file) || !is_readable($file)) {
            return false;
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return in_array($ext, $this->site->support->support, true);
    }

    /**
     * @param  string $file
     * @return array
     */
    public function getFileInfo(string $file): array
    {
        return [
            'ext' => pathinfo($file, PATHINFO_EXTENSION),
            'filename' => pathinfo($file, PATHINFO_FILENAME)
        ];
    }

    /**
     * @param  string $file
     * @return string
     */
    public function generateBaseSlug(string $file): string
    {
        $slug = str_replace($this->base . DIRECTORY_SEPARATOR . '_content', "", $file);
        $slug = ltrim($slug, DIRECTORY_SEPARATOR);
        return preg_replace("/^" . $this->site->contentdir . "/", "", $slug);
    }

    /**
     * @param  array $page
     * @return string
     */
    public function determineLayout(array $page): string
    {
        $layout = "page";

        if (isset($page["layout"])) {
            $layoutFile = $this->base . DIRECTORY_SEPARATOR . "_template" . DIRECTORY_SEPARATOR . $page["layout"] . ".php";
            if (file_exists($layoutFile) && is_readable($layoutFile)) {
                return $page["layout"];
            }
        }

        $slugParts = explode("/", trim($page["slug"], "/"));
        if (count($slugParts) > 1) {
            $folderName = trim($slugParts[count($slugParts) - 2]);
            $layoutFile = $this->base . DIRECTORY_SEPARATOR . "_template" . DIRECTORY_SEPARATOR . $folderName . ".php";
            if (file_exists($layoutFile) && is_readable($layoutFile)) {
                return $folderName;
            }
        }

        return $layout;
    }
}
