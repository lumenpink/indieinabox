<?php

namespace Indieinabox;

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
     * @var object
     */
    private $site;

    /**
     * @param FileProcessor     $fileProcessor
     * @param ContentProcessor  $contentProcessor
     * @param LanguageProcessor $languageProcessor
     * @param object            $site
     */
    public function __construct(
        FileProcessor $fileProcessor,
        ContentProcessor $contentProcessor,
        LanguageProcessor $languageProcessor,
        object $site
    ) {
        $this->fileProcessor = $fileProcessor;
        $this->contentProcessor = $contentProcessor;
        $this->languageProcessor = $languageProcessor;
        $this->site = $site;
    }

    /**
     * @param  string $file
     * @return array|false|null
     */
    public function parse(string $file)
    {
        if (!$this->fileProcessor->isValidFile($file)) {
            return false;
        }

        $fileInfo = $this->fileProcessor->getFileInfo($file);
        $content = file_get_contents($file);

        $page = $this->contentProcessor->extractFrontMatter($content);
        $content = $this->contentProcessor->removeYamlFrontMatter($content);

        $page = $this->contentProcessor->setTitle($page, $content, $this->site->defaulttitle);
        $page = $this->contentProcessor->processTags($page, $content);

        $content = $this->contentProcessor->processContent($content);
        $page['content'] = trim($content, " \n\r\t");

        if (!$this->site->buildall && empty($page)) {
            return null;
        }

        $page = $this->languageProcessor->processLanguage($page);
        $page["layout"] = $this->fileProcessor->determineLayout($page, $fileInfo);
        $page = $this->setMetadata($page);

        return $page;
    }

    /**
     * @param  array $page
     * @return array
     */
    private function setMetadata(array $page): array
    {
        if (!isset($page["default-category"])) {
            $page["default-category"] = "General";
        }

        if (!isset($page["category"])) {
            $page["category"] = $page["default-category"];
        }

        $kindResult = kind($page);
        $page["localizedkind"] = $kindResult["localized"];
        $page["kind"] = $kindResult["kind"];

        $dateResult = localizeddate($page);
        $page["localizeddate"] = $dateResult["long"];
        $page["isodate"] = $dateResult["iso"];

        return $page;
    }
}
