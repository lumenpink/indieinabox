<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\LanguageProcessor;

class MarkdownParser
{
    /**
     * @var FileProcessor
     */
    private $fileProcessor;

    /**
     * @var ContentProcessor
     */
    private $contentProcessor;

    /**
     * @var LanguageProcessor
     */
    private $languageProcessor;

    /**
     * @var \Indieinabox\Site
     */
    private $site;

    /**
     * @param FileProcessor     $fileProcessor
     * @param ContentProcessor  $contentProcessor
     * @param LanguageProcessor $languageProcessor
     * @param \Indieinabox\Site $site
     */
    public function __construct(
        FileProcessor $fileProcessor,
        ContentProcessor $contentProcessor,
        LanguageProcessor $languageProcessor,
        \Indieinabox\Site $site
    ) {
        $this->fileProcessor = $fileProcessor;
        $this->contentProcessor = $contentProcessor;
        $this->languageProcessor = $languageProcessor;
        $this->site = $site;
    }

    /**
     * @param  string $file
     * @return Page|false|null
     */
    public function parse(string $file)
    {
        if (!$this->fileProcessor->isValidFile($file)) {
            return false;
        }

        $fileInfo = $this->fileProcessor->getFileInfo($file);
        $content = file_get_contents($file);
        if ($content === false) {
            return false;
        }

        $page = $this->contentProcessor->extractFrontMatter($content);
        $content = $this->contentProcessor->removeYamlFrontMatter($content);

        $hasFrontMatter = !empty($page);

        $page = $this->contentProcessor->setTitle($page, $content, $this->site->metadata->defaultTitle);
        $page = $this->contentProcessor->setDate($page, $file);
        $page = $this->contentProcessor->processTags($page, $content);

        $content = $this->contentProcessor->processContent($content);
        $page['content'] = trim($content, " \n\r\t");

        if (!$this->site->options->buildAll && !$hasFrontMatter) {
            return null;
        }

        // Process slug
        $slug = $this->fileProcessor->generateBaseSlug($file);
        if ($fileInfo['filename'] === "index") {
            $slug = str_replace($fileInfo['filename'] . "." . $fileInfo['ext'], "", $slug);
        } else {
            $slug = str_replace("." . $fileInfo['ext'], "", $slug);
        }

        if (isset($page["slug"])) {
            $slug = str_replace($fileInfo['filename'], $page["slug"], $slug);
        }

        $slug = trim($slug, DIRECTORY_SEPARATOR);
        $slug = str_replace(DIRECTORY_SEPARATOR, "/", $slug);
        $slug = strtolower($slug);
        $slug = rtrim($slug, "/") . "/";
        $page["slug"] = $slug;

        // Calculate relative path
        $parts = explode('/', rtrim($slug, '/'));
        if ($slug === '/') {
            $page["relpath"] = './';
        } else {
            $page["relpath"] = str_repeat('../', count($parts));
        }

        // Determine layout
        $layout = $this->fileProcessor->determineLayout($page);
        $page["layout"] = $layout;

        // Convert to Page object and process language & metadata
        $pageObj = Page::fromArray($page);
        $pageObj = $this->languageProcessor->processLanguage($pageObj);
        $pageObj = $this->setMetadata($pageObj);

        return $pageObj;
    }

    /**
     * @param  Page $page
     * @return Page
     */
    private function setMetadata(Page $page): Page
    {
        if (empty($page->category) || $page->category === ["No Category"]) {
            $page->category = ["General"];
        }

        $kindResult = Helper::kind($page);
        $page->localizedkind = $kindResult["localized"];
        $page->kind = $kindResult["kind"];

        $dateResult = Helper::localizeddate($page);
        $page->localizeddate = $dateResult["long"];

        return $page;
    }
}
