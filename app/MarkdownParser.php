<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\LanguageProcessor;

class MarkdownParser implements ParserInterface
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

        $page['rawBody'] = trim($content, " \n\r\t");

        if (!$this->site->options->buildAll && !$hasFrontMatter) {
            return null;
        }

        // Active languages mapping
        $langs = $this->site->localization->lang;
        if (!is_array($langs)) {
            $langs = [$langs];
        }
        $defaultLang = $this->site->localization->defaultLang ?? 'en';

        // Calculate path relative to the content directory
        $contentDir = $this->site->paths->contentDir;
        $baseDir = $this->site->paths->baseDir;
        $relPath = str_replace($baseDir . DIRECTORY_SEPARATOR . $contentDir, "", $file);
        $relPath = ltrim($relPath, DIRECTORY_SEPARATOR);
        $relPath = str_replace(DIRECTORY_SEPARATOR, "/", $relPath);

        // Detect language only from path top-level subdirectory
        $segments = explode('/', $relPath);
        $detectedLang = $defaultLang;
        $cleanRelPath = $relPath;
        if (isset($segments[0]) && in_array($segments[0], $langs, true)) {
            $detectedLang = $segments[0];
            array_shift($segments);
            $cleanRelPath = implode('/', $segments);
        }

        // Process base slug from clean relative path
        $cleanFilename = $fileInfo['filename'];
        $slugBase = $cleanRelPath;
        if (str_ends_with($slugBase, '.' . $fileInfo['ext'])) {
            $slugBase = substr($slugBase, 0, -(strlen($fileInfo['ext']) + 1));
        }

        if ($cleanFilename === "index") {
            if (str_ends_with($slugBase, 'index')) {
                $slugBase = substr($slugBase, 0, -5);
            }
        }

        if (isset($page["slug"])) {
            $slugBase = str_replace($cleanFilename, $page["slug"], $slugBase);
        }

        $slugBase = trim($slugBase, '/');
        $slugBase = strtolower($slugBase);

        // Build final slug with language prefix if non-default
        $finalSlug = $slugBase;
        if ($detectedLang !== $defaultLang) {
            $finalSlug = $detectedLang . ($finalSlug !== '' ? '/' . $slugBase : '');
        }

        $isIndex = ($fileInfo['filename'] === "index" || (isset($page["slug"]) && str_starts_with($page["slug"], "index")));
        $prettylinks = $this->site->options->prettylinks ?? true;
        if ($prettylinks) {
            $finalSlug = $finalSlug !== '' ? rtrim($finalSlug, "/") . "/" : "/";
        } else {
            if ($isIndex) {
                $finalSlug = $finalSlug !== '' ? rtrim($finalSlug, "/") . "/" : "/";
            } else {
                $finalSlug = $finalSlug !== '' ? rtrim($finalSlug, "/") . ".html" : "index.html";
            }
        }
        $page["slug"] = $finalSlug;

        // Calculate relative path
        $cleanSlug = ltrim($finalSlug, '/');
        if ($cleanSlug === '' || $cleanSlug === 'index.html') {
            $page["relpath"] = './';
        } else {
            $slashCount = substr_count($cleanSlug, '/');
            $page["relpath"] = $slashCount > 0 ? str_repeat('../', $slashCount) : './';
        }

        // Determine layout
        $layout = $this->fileProcessor->determineLayout($page);
        $page["layout"] = $layout;

        // Convert to Page object and process language & metadata
        $page['lang'] = $detectedLang;
        $pageObj = Page::fromArray($page);
        $pageObj->filepath = $file;
        $pageObj = $this->languageProcessor->processLanguage($pageObj);
        $pageObj = $this->setMetadata($pageObj, $page);

        $renderedContent = $this->contentProcessor->processContent($content, $pageObj);
        $pageObj->content->content = trim($renderedContent, " \n\r\t");

        return $pageObj;
    }

    /**
     * @param  Page $page
     * @param  array $rawPage
     * @return Page
     */
    private function setMetadata(Page $page, array $rawPage): Page
    {
        if (empty($page->category) || $page->category === ["No Category"]) {
            $page->category = ["General"];
        }

        $kindResult = Helper::kind($rawPage);
        $page->localizedkind = $kindResult["localized"];
        $page->kind = $kindResult["kind"];

        // Translate/localize the folder name in the slug if it maps to this kind
        if ($page->kind !== 'generic' && $page->kind !== 'page' && $page->kind !== 'home') {
            $slug = $page->slug;
            $parts = explode('/', $slug);
            $folderIndex = ($page->lang === $this->site->localization->defaultLang) ? 0 : 1;

            if (isset($parts[$folderIndex])) {
                $oldFolder = $parts[$folderIndex];
                $matchedKind = null;
                global $kindspath;
                if (!empty($this->site->config['kinds'])) {
                    foreach ($this->site->config['kinds'] as $k => $conf) {
                        if (($conf['content_dir'] ?? $k) === $oldFolder) {
                            $matchedKind = $k;
                            break;
                        }
                    }
                }
                if ($matchedKind === null && !empty($kindspath)) {
                    foreach ($kindspath as $key => $value) {
                        if (in_array($oldFolder, $value)) {
                            $matchedKind = $key;
                            break;
                        }
                    }
                }

                if ($matchedKind === $page->kind) {
                    $parts[$folderIndex] = $page->localizedkind;
                    $page->slug = implode('/', $parts);

                    // Re-calculate the relative path based on the updated slug
                    $cleanSlug = ltrim($page->slug, '/');
                    if ($cleanSlug === '' || $cleanSlug === 'index.html') {
                        $page->relpath = './';
                    } else {
                        $slashCount = substr_count($cleanSlug, '/');
                        $page->relpath = $slashCount > 0 ? str_repeat('../', $slashCount) : './';
                    }
                }
            }
        }

        $dateResult = Helper::localizeddate($page);
        $page->localizeddate = $page->isodate;

        return $page;
    }
}
