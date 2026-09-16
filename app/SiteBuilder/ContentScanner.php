<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Markdown\MarkdownParser;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Markdown\ParserInterface;
use Indieinabox\Site\Site;
use Indieinabox\Translations\UrlTranslations;

/**
 * Scans directories for Markdown content files and manages initial page collection.
 */
class ContentScanner
{
    private Site $site;
    private ParserInterface $parser;

    public function __construct(Site $site, ?ParserInterface $parser = null)
    {
        $this->site = $site;

        if ($parser !== null) {
            $this->parser = $parser;
        } else {
            $base = $this->site->paths->baseDir;
            global $urltranslations;

            $fileProcessor     = new FileProcessor($this->site, $base);
            $contentProcessor  = new ContentProcessor();
            $urlTranslationsObj   = new UrlTranslations($urltranslations ?? []);
            $languageProcessor = new LanguageProcessor($this->site, $urlTranslationsObj);

            $this->parser = new MarkdownParser(
                $fileProcessor,
                $contentProcessor,
                $languageProcessor,
                $this->site
            );
        }
    }

    public function getParser(): ParserInterface
    {
        return $this->parser;
    }

    /**
     * Recursively scans a directory for markdown content files.
     * Parses valid markdown files into Page objects and adds them to the collection.
     * Skips system directories (e.g., app, vendor, output dirs).
     *
     * @param string $dir The directory path to scan.
     * @param Pages $pages The collection to populate.
     * @return void
     */
    public function scan(string $dir, Pages $pages): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if (
                $entry !== '.'
                && $entry !== '..'
                && substr($entry, 0, 1) !== '_'
                && substr($entry, 0, 1) !== '.'
            ) {
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path)) {
                    if ($entry === 'intro.md') {
                        continue;
                    }
                    $page = $this->parser->parse($path);
                    if ($page) {
                        $pages->add($page);
                    }
                } elseif (is_dir($path)) {
                    $baseDir = rtrim($this->site->paths->baseDir ?? '', DIRECTORY_SEPARATOR);
                    $themeDir = $this->site->paths->themeDir ?? 'theme';
                    $ignoredDirs = [
                        $baseDir . DIRECTORY_SEPARATOR . 'app',
                        $baseDir . DIRECTORY_SEPARATOR . 'bootstrap',
                        $baseDir . DIRECTORY_SEPARATOR . 'vendor',
                        $baseDir . DIRECTORY_SEPARATOR . 'resources',
                        $baseDir . DIRECTORY_SEPARATOR . 'theme',
                        $baseDir . DIRECTORY_SEPARATOR . 'data',
                        $baseDir . DIRECTORY_SEPARATOR . $themeDir,
                        $baseDir . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml,
                        $baseDir . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGemini,
                        $baseDir . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGopher,
                        $baseDir . DIRECTORY_SEPARATOR . $this->site->paths->outputDirMedia,
                    ];
                    $skip = false;
                    foreach ($ignoredDirs as $ignored) {
                        if ($path === $ignored || strpos($path, $ignored . DIRECTORY_SEPARATOR) === 0) {
                            $skip = true;
                            break;
                        }
                    }
                    if (!$skip) {
                        $this->scan($path, $pages);
                    }
                }
            }
        }
    }

    /**
     * Ensures a mandatory homepage (index.html) exists in the output.
     * If one was not provided in the content directory, it creates a generic fallback.
     *
     * @param Pages $pages
     * @return void
     */
    public function ensureMandatoryHomepage(Pages $pages): void
    {
        $langs = (array) ($this->site->localization->lang ?? ['en']);
        $defaultLang = (string) ($this->site->localization->defaultLang ?? 'en');

        foreach ($langs as $lang) {
            $expectedSlug = ($lang === $defaultLang) ? '/' : $lang . '/';
            $found = false;
            foreach ($pages as $p) {
                if ($p->slug === $expectedSlug || rtrim($p->slug, '/') === rtrim($expectedSlug, '/')) {
                    $found = true;
                    // Force the layout to home just in case
                    $p->layout = 'home';
                    break;
                }
            }
            if (!$found) {
                $page = Page::fromArray([
                    'slug' => $expectedSlug,
                    'nick' => 'index',
                    'kind' => 'generic',
                    'title' => $this->site->metadata->title ?? 'Home',
                    'lang' => $lang,
                    'layout' => 'home',
                    'content' => '',
                    'relpath' => ($lang === $defaultLang) ? '' : '../'
                ]);
                $pages->add($page);
            }
        }
    }

    /**
     * Renders raw markdown bodies into final HTML content for all pages in the collection.
     * Sets global variables $pages and $site for template and processor compatibility.
     *
     * @param Pages $pageCollection The collection of pages to render.
     * @return void
     */
    public function renderRawBodies(Pages $pageCollection): void
    {
        global $pages, $site;
        $pages = $pageCollection;
        $site = $this->site;

        $contentProcessor = new ContentProcessor();
        foreach ($pageCollection as $page) {
            if (isset($page->rawBody) && $page->rawBody !== '') {
                $renderedContent = $contentProcessor->processContent($page->rawBody, $page);
                $page->content->content = trim($renderedContent, " \n\r\t");
            }
        }
    }
}
