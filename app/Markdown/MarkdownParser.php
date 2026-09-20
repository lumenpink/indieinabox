<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

use Indieinabox\Core\Database;
use Indieinabox\Page\Page;
use Indieinabox\Site\Site;
use Indieinabox\Support\DateFormatter;
use Indieinabox\Support\TextParser;
use Indieinabox\Taxonomy\KindHelper;

/**
 * Class MarkdownParser
 *
 * Coordinates parsing a markdown file into a typed Page object:
 * validates extensions, extracts YAML frontmatter, detects languages,
 * builds canonical slugs, determines layouts, and maps metadata.
 */
class MarkdownParser implements ParserInterface
{
    /**
     * @var FileProcessor
     */
    private FileProcessor $fileProcessor;

    /**
     * @var ContentProcessor
     */
    private ContentProcessor $contentProcessor;

    /**
     * @var LanguageProcessor
     */
    private LanguageProcessor $languageProcessor;

    /**
     * @var Site
     */
    private Site $site;

    /**
     * @param FileProcessor $fileProcessor
     * @param ContentProcessor $contentProcessor
     * @param LanguageProcessor $languageProcessor
     * @param Site $site
     */
    public function __construct(
        FileProcessor $fileProcessor,
        ContentProcessor $contentProcessor,
        LanguageProcessor $languageProcessor,
        Site $site
    ) {
        $this->fileProcessor = $fileProcessor;
        $this->contentProcessor = $contentProcessor;
        $this->languageProcessor = $languageProcessor;
        $this->site = $site;
    }

    /**
     * @return FileProcessor
     */
    public function getFileProcessor(): FileProcessor
    {
        return $this->fileProcessor;
    }

    /**
     * @return ContentProcessor
     */
    public function getContentProcessor(): ContentProcessor
    {
        return $this->contentProcessor;
    }

    /**
     * @return LanguageProcessor
     */
    public function getLanguageProcessor(): LanguageProcessor
    {
        return $this->languageProcessor;
    }

    /**
     * @return Site
     */
    public function getSite(): Site
    {
        return $this->site;
    }

    /**
     * Parses a markdown file from disk into a populated Page object.
     *
     * @param string $file The path to the markdown file.
     * @return Page|null The parsed page or null if invalid or skipped.
     */
    #[\Override]
    public function parse(string $file): ?Page
    {
        if (!$this->fileProcessor->isValidFile($file)) {
            return null;
        }

        $fileInfo = $this->fileProcessor->getFileInfo($file);
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $page = $this->contentProcessor->extractFrontMatter($content);
        $content = $this->contentProcessor->removeYamlFrontMatter($content);

        $hasFrontMatter = !empty($page);

        $page = $this->contentProcessor->setTitle($page, $content, $fileInfo['filename']);
        $page = $this->contentProcessor->setDate($page, $file);
        $page = $this->contentProcessor->processTags($page, $content);

        $page['rawBody'] = trim($content, " \n\r\t");

        if (isset($page['publish']) && $page['publish'] === false) {
            return null;
        }

        if (!$this->site->options->buildAll && !$hasFrontMatter) {
            return null;
        }

        // Active languages mapping
        $langs = (array) ($this->site->localization->lang ?? ['en']);
        $defaultLang = (string) ($this->site->localization->defaultLang ?? 'en');

        // Calculate path relative to the content directory
        $contentDir = $this->site->paths->contentDir;
        $baseDir = $this->site->paths->baseDir;
        $relPath = str_replace($baseDir . DIRECTORY_SEPARATOR . $contentDir, "", $file);
        $relPath = ltrim($relPath, DIRECTORY_SEPARATOR);
        $relPath = str_replace(DIRECTORY_SEPARATOR, "/", $relPath);

        // Detect language only from path top-level subdirectory
        [$detectedLang, $cleanRelPath, $isRoot] = $this->detectLanguage($relPath, $langs, $defaultLang);

        $hasPublishTrue = isset($page['publish']) && $page['publish'] === true;
        if ($isRoot && !$hasPublishTrue) {
            $page['kind'] = 'page';
        }

        // Process base slug and build final slug
        $finalSlug = $this->buildSlug($cleanRelPath, $fileInfo, $page, $detectedLang, $defaultLang);
        $page["slug"] = $finalSlug;

        // Calculate relative path for web links
        $page["relpath"] = $this->calculateRelativePath($finalSlug);

        // Determine layout
        $layout = $this->fileProcessor->determineLayout($page);
        $page["layout"] = $layout;

        // Convert to Page object and process language & metadata
        $page['lang'] = $detectedLang;
        $pageObj = Page::fromArray($page);
        $pageObj->filepath = $file;
        $pageObj = $this->languageProcessor->processLanguage($pageObj);
        $pageObj = $this->setMetadata($pageObj, $page);

        return $pageObj;
    }

    /**
     * Detects page language from the top-level directory segment.
     *
     * @param string $relPath
     * @param string[] $langs
     * @param string $defaultLang
     * @return array{0: string, 1: string, 2: bool} [detectedLang, cleanRelPath, isRoot]
     */
    public function detectLanguage(string $relPath, array $langs, string $defaultLang): array
    {
        $segments = explode('/', $relPath);
        $detectedLang = $defaultLang;
        $cleanRelPath = $relPath;

        if (isset($segments[0]) && in_array($segments[0], $langs, true)) {
            $detectedLang = $segments[0];
            array_shift($segments);
            $cleanRelPath = implode('/', $segments);
        }

        $isRoot = count($segments) === 1;

        return [$detectedLang, $cleanRelPath, $isRoot];
    }

    /**
     * Builds the canonical slug for a page based on its relative path and settings.
     *
     * @param string $cleanRelPath
     * @param array<string, mixed> $fileInfo
     * @param array<string, mixed> $page
     * @param string $detectedLang
     * @param string $defaultLang
     * @return string
     */
    public function buildSlug(
        string $cleanRelPath,
        array $fileInfo,
        array $page,
        string $detectedLang,
        string $defaultLang
    ): string {
        $cleanFilename = (string) $fileInfo['filename'];
        $ext = (string) $fileInfo['ext'];
        $slugBase = $cleanRelPath;

        if (str_ends_with($slugBase, '.' . $ext)) {
            $slugBase = substr($slugBase, 0, -(strlen($ext) + 1));
        }

        if ($cleanFilename === "index") {
            if (str_ends_with($slugBase, 'index')) {
                $slugBase = substr($slugBase, 0, -5);
            }
        }

        if (isset($page["slug"])) {
            $slugBase = str_replace($cleanFilename, (string) $page["slug"], $slugBase);
        }

        $slugBase = trim($slugBase, '/');
        $slugBaseParts = explode('/', $slugBase);
        $slugBaseParts = array_map([TextParser::class, 'slugize'], $slugBaseParts);
        $slugBase = implode('/', $slugBaseParts);

        // Build final slug with language prefix if non-default
        $finalSlug = $slugBase;
        if ($detectedLang !== $defaultLang) {
            $finalSlug = $detectedLang . ($finalSlug !== '' ? '/' . $slugBase : '');
        }

        $isIndex = ($cleanFilename === "index" || (isset($page["slug"]) && str_starts_with((string) $page["slug"], "index")));
        $prettylinks = $this->site->options->prettylinks ?? true;
        if ($prettylinks) {
            return $finalSlug !== '' ? rtrim($finalSlug, "/") . "/" : "/";
        }

        if ($isIndex) {
            return $finalSlug !== '' ? rtrim($finalSlug, "/") . "/" : "/";
        }

        return $finalSlug !== '' ? rtrim($finalSlug, "/") . ".html" : "index.html";
    }

    /**
     * Calculates the relative traversal path (e.g., './' or '../../') based on slug depth.
     *
     * @param string $slug
     * @return string
     */
    public function calculateRelativePath(string $slug): string
    {
        $cleanSlug = ltrim($slug, '/');
        if ($cleanSlug === '' || $cleanSlug === 'index.html') {
            return './';
        }

        $slashCount = substr_count($cleanSlug, '/');
        return $slashCount > 0 ? str_repeat('../', $slashCount) : './';
    }

    /**
     * Applies metadata, localized kind mappings, and localized date formatting to the Page object.
     *
     * @param Page $page
     * @param array<string, mixed> $rawPage
     * @return Page
     */
    private function setMetadata(Page $page, array $rawPage): Page
    {
        if (empty($page->category) || $page->category === ["No Category"]) {
            $page->category = ["General"];
        }

        $kindResult = KindHelper::kind($rawPage, $this->site);
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
                $kindsPath = $this->site->config['kindspath'] ?? Database::getSetting('kindspath', []);
                if (!empty($this->site->config['kinds'])) {
                    foreach ($this->site->config['kinds'] as $k => $conf) {
                        $cDir = $conf['content_dir'] ?? $k;
                        if (is_array($cDir)) {
                            if (in_array($oldFolder, $cDir, true)) {
                                $matchedKind = $k;
                                break;
                            }
                        } elseif ($cDir === $oldFolder) {
                            $matchedKind = $k;
                            break;
                        }
                    }
                }

                // Fallback to legacy folder names
                if ($matchedKind === null && !empty($kindsPath)) {
                    foreach ($kindsPath as $key => $value) {
                        if (in_array($oldFolder, $value, true)) {
                            $matchedKind = $key;
                            break;
                        }
                    }
                }

                if ($matchedKind === $page->kind) {
                    $parts[$folderIndex] = TextParser::slugize($page->localizedkind);
                    $page->slug = implode('/', $parts);

                    // Re-calculate the relative path based on the updated slug
                    $page->relpath = $this->calculateRelativePath($page->slug);
                }
            }
        }

        DateFormatter::localizeddate($page);
        $page->localizeddate = $page->date->format('Y-m-d');

        return $page;
    }
}
