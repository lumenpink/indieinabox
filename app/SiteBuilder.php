<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Translations\UrlTranslations;
use Indieinabox\Markdown\ASTParser;
use Indieinabox\Markdown\GemtextRenderer;
use Indieinabox\Markdown\GophermapRenderer;

/**
 * Class SiteBuilder
 * 
 * Orchestrates the static site generation process. It scans the content directory,
 * virtualizes missing translations, processes markdown into HTML/Gemtext/Gophermap,
 * and compiles feeds and assets into the output directory.
 */
class SiteBuilder
{
    /**
     * @var \Indieinabox\Site
     */
    private Site $site;
    /**
     * @var \Indieinabox\Pages
     */
    private Pages $pages;
    /**
     * @var \Indieinabox\ParserInterface
     */
    private ParserInterface $parser;

    /**
     * SiteBuilder constructor.
     *
     * @param \Indieinabox\Site $site The site configuration and environment settings.
     * @param \Indieinabox\Pages|null $pages An optional collection of parsed pages.
     * @param \Indieinabox\ParserInterface|null $parser An optional markdown parser implementation.
     */
    public function __construct(Site $site, ?Pages $pages = null, ?ParserInterface $parser = null)
    {
        $this->site = $site;
        $this->pages = $pages ?? new Pages();

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

    /**
     * Retrieves the collection of processed pages.
     *
     * @return \Indieinabox\Pages The pages collection.
     */
    public function getPages(): Pages
    {
        return $this->pages;
    }

    /**
     * Stores absolute paths of all generated files during the build process
     * for Garbage Collection.
     * @var string[]
     */
    public static array $manifest = [];

    /**
     * Adds a file path to the manifest array.
     * 
     * @param string $path
     * @return void
     */
    public static function addManifest(string $path): void
    {
        self::$manifest[$path] = true;
    }

    /**
     * Executes the main build pipeline.
     * 
     * Cleans the output directory, scans content files, handles translation virtualization,
     * and triggers generation of HTML, feeds, and static assets.
     */
    public function build(): void
    {
        $base = $this->site->paths->baseDir;
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $timings = [];
        $t_start = microtime(true);

        // Scan content
        $s1 = microtime(true);
        $this->scan($this->site->paths->getContentPath());
        $this->ensureMandatoryHomepage();
        $this->virtualizeMissingLanguages();
        $s2 = microtime(true);
        $timings['Scan + Virtualize'] = ($s2 - $s1) * 1000;

        // Generate files
        $this->generateHTMLFiles();
        $s3 = microtime(true);
        $timings['Generate HTML/GMI/Gopher'] = ($s3 - $s2) * 1000;
        
        // Twtxt update is now handled by cron/BackgroundWorker to avoid online dependencies during build,
        // but we still generate local feeds and static timeline from the cache.
        $this->generateTwtxt();
        $this->generateFeed();
        $s4 = microtime(true);
        $timings['Generate Feeds'] = ($s4 - $s3) * 1000;

        // Copy assets
        $this->copyAssets($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views");
        $s5 = microtime(true);
        $timings['Copy Assets'] = ($s5 - $s4) * 1000;

        // Copy Media
        $this->copyMedia();
        $s6 = microtime(true);
        $timings['Copy Media'] = ($s6 - $s5) * 1000;

        // Copy static files
        if ($this->site->options->skipStatic) {
            echo "Skipping static files\n";
        } else {
            $this->copyStatic($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "static");
        }
        $s7 = microtime(true);
        $timings['Copy Static Files'] = ($s7 - $s6) * 1000;

        $this->garbageCollect();
        $s8 = microtime(true);
        $timings['Garbage Collect'] = ($s8 - $s7) * 1000;

        $totalTime = ($s8 - $t_start) * 1000;

        // Output summary table
        echo "\n+----------------------------------+-----------------+\n";
        echo "| Task                             | Time (ms)       |\n";
        echo "+----------------------------------+-----------------+\n";
        foreach ($timings as $task => $time) {
            printf("| %-32s | %15.2f |\n", $task, $time);
        }
        echo "+----------------------------------+-----------------+\n";
        printf("| %-32s | %15.2f |\n", 'TOTAL BUILD TIME', $totalTime);
        echo "+----------------------------------+-----------------+\n";
    }

    /**
     * Scans output directories and removes files not registered in the manifest.
     * Removes empty directories as well.
     */
    private function garbageCollect(): void
    {
        echo "Running Garbage Collector...\n";
        $base = $this->site->paths->baseDir;
        $dirs = [
            $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml,
            $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGemini,
            $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGopher,
            $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirMedia,
        ];

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $this->cleanOrphanedFiles($dir);
            }
        }
    }

    /**
     * Recursively deletes orphaned files and empty directories.
     */
    private function cleanOrphanedFiles(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanOrphanedFiles($path);
                // After cleaning contents, check if directory is empty
                $contents = scandir($path);
                if ($contents !== false && count($contents) <= 2) {
                    rmdir($path);
                }
            } elseif (is_file($path)) {
                // Remove if not in manifest
                if (!isset(self::$manifest[$path])) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Copies static media files from the content directory to the public media output directory.
     * Preserves directory structures and handles file deduplication.
     * 
     * @return void
     */
    public function copyMedia(): void
    {
        $base = $this->site->paths->baseDir;
        $contentMediaDir = rtrim($this->site->paths->getContentPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'media';
        if (!is_dir($contentMediaDir)) {
            return;
        }

        echo "Copying media files\n";
        ThemeManager::copyStaticFiles($contentMediaDir, $base, $this->site->paths->outputDirMedia);
    }
    /**
     * Generates pseudo-translated pages for missing languages to maintain parity.
     * Uses configured rules (e.g., full parity, from-main-only) and translates 
     * missing slugs according to URL translation mappings.
     *
     * @return void
     */
    private function virtualizeMissingLanguages(): void
    {
        $langs = $this->site->localization->lang;
        if (count($langs) <= 1) {
            return;
        }

        $defaultLang = $this->site->localization->defaultLang ?? 'en';
        $prettylinks = $this->site->options->prettylinks ?? true;
        
        $parity = $this->site->options->translation_parity ?? 'full';
        if ($parity === 'disabled') {
            return;
        }
        $autoVirtualize = $this->site->options->translation_auto ?? 'pseudo';

        global $urltranslations;
        $urlTranslationsArr = $urltranslations ?? [];
        $reverseTranslations = [];
        foreach ($urlTranslationsArr as $defaultNick => $translations) {
            foreach ($translations as $l => $translatedNick) {
                $reverseTranslations[$l][$translatedNick] = $defaultNick;
            }
        }

        $existing = [];
        $pagesToProcess = [];
        foreach ($this->pages as $page) {
            $lang = $page->lang ?? $defaultLang;
            $nick = $page->nick ?? '';
            $kind = $page->kind ?? '';

            $existing["{$kind}:{$nick}:{$lang}"] = $page;
            $pagesToProcess[] = $page;
        }

        foreach ($pagesToProcess as $page) {
            if (in_array($page->kind, ['generic'], true)) {
                if ($page->slug !== '' && $page->slug !== 'index.html' && $page->slug !== '/') {
                    continue;
                }
            }

            $sourceLang = $page->lang ?? $defaultLang;
            
            // Find base nick
            $baseNick = $page->nick;
            if ($sourceLang !== $defaultLang) {
                if (isset($reverseTranslations[$sourceLang][$page->nick])) {
                    $baseNick = $reverseTranslations[$sourceLang][$page->nick];
                }
            }

            $sourceIsMain = ($sourceLang === $defaultLang);

            foreach ($langs as $targetLang) {
                if ($targetLang === $sourceLang) {
                    continue;
                }
                
                $targetIsMain = ($targetLang === $defaultLang);
                
                if ($parity === 'from-main-only' && !$sourceIsMain) {
                    continue;
                }
                if ($parity === 'from-sublang-only' && $sourceIsMain) {
                    continue;
                }
                if ($parity === 'inter-sublang-only' && ($sourceIsMain || $targetIsMain)) {
                    continue;
                }

                $targetNick = $baseNick;
                if ($targetLang !== $defaultLang) {
                    if (isset($urlTranslationsArr[$baseNick][$targetLang])) {
                        $targetNick = $urlTranslationsArr[$baseNick][$targetLang];
                    }
                }

                $key = "{$page->kind}:{$targetNick}:{$targetLang}";
                if (!isset($existing[$key])) {
                    if ($autoVirtualize === 'disabled') {
                        throw new \RuntimeException(
                            "Translation Parity rule '{$parity}' violated. " .
                            "Missing translation for '{$page->slug}' in '{$targetLang}'."
                        );
                    }
                    
                    $existing[$key] = true; // Mark as handled

                    if (php_sapi_name() === 'cli') {
                        echo "[WARNING] Missing translation for page '{$page->slug}'"
                            . " in language '{$targetLang}'. Virtualizing...\n";
                    }

                    $cloned = clone $page;
                    $cloned->lang = $targetLang;
                    $cloned->nick = $targetNick;

                    $this->pseudoTranslate($cloned, $targetLang);

                    $kindFolder = $this->getKindFolder($cloned->kind, $targetLang);
                    $sourceKindFolder = $this->getKindFolder($page->kind, $sourceLang);
                    
                    if (in_array($sourceKindFolder, ['page', 'generic', 'home'], true)) $sourceKindFolder = '';
                    if (in_array($kindFolder, ['page', 'generic', 'home'], true)) $kindFolder = '';
                    
                    $cleanSlug = trim($page->slug, '/');
                    $sourceLangPrefix = $sourceLang !== $defaultLang ? $sourceLang . '/' : '';
                    $sourcePrefix = $sourceLangPrefix . $sourceKindFolder;
                    $sourcePrefix = trim($sourcePrefix, '/');
                    
                    if ($sourcePrefix !== '' && str_starts_with($cleanSlug, $sourcePrefix . '/')) {
                        $cleanSlug = substr($cleanSlug, strlen($sourcePrefix . '/'));
                    } elseif ($sourcePrefix !== '' && $cleanSlug === $sourcePrefix) {
                        $cleanSlug = '';
                    }

                    if ($cleanSlug === '' || $cleanSlug === 'index.html') {
                        $cloned->slug = $targetLang !== $defaultLang ? $targetLang . '/index.html' : 'index.html';
                    } else {
                        $targetPrefix = $targetLang !== $defaultLang ? $targetLang . '/' : '';
                        if ($kindFolder !== '') {
                            $targetPrefix .= $kindFolder . '/';
                        }
                        
                        if ($prettylinks) {
                            $cloned->slug = $targetPrefix . $cleanSlug . '/';
                        } else {
                            if (str_ends_with($cleanSlug, '.html')) {
                                $cleanSlug = substr($cleanSlug, 0, -5);
                            }
                            $cloned->slug = $targetPrefix . $cleanSlug . '.html';
                        }
                    }

                    $cloned->slug = trim(str_replace('//', '/', $cloned->slug), '/');
                    if ($prettylinks && !str_ends_with($cloned->slug, '.html') && $cloned->slug !== '' && $cloned->slug !== 'index.html') {
                        $cloned->slug .= '/';
                    }

                    $cleanSlugPath = ltrim($cloned->slug, '/');
                    if ($cleanSlugPath === '' || $cleanSlugPath === 'index.html') {
                        $cloned->relpath = './';
                    } else {
                        $slashCount = substr_count($cleanSlugPath, '/');
                        $cloned->relpath = $slashCount > 0 ? str_repeat('../', $slashCount) : './';
                    }

                    $urlTranslationsObj = new UrlTranslations($urlTranslationsArr);
                    $languageProcessor = new LanguageProcessor($this->site, $urlTranslationsObj);
                    $cloned = $languageProcessor->processLanguage($cloned);

                    $this->pages->add($cloned);
                }
            }
        }
    }

    /**
     * Applies a pseudo-translation prefix to a page's title or content.
     * Used visually to flag that a page was automatically virtualized.
     *
     * @param \Indieinabox\Page $page The page to translate in place.
     * @param string $targetLang The target language code used as the prefix.
     * @return void
     */
    public function pseudoTranslate(\Indieinabox\Page $page, string $targetLang): void
    {
        $prefix = '[' . strtoupper($targetLang) . '] ';
        $hasTitle = !empty($page->title)
            && $page->title !== 'Untitled'
            && $page->title !== 'untitled';

        $kindConfig = \Indieinabox\Helper::getKindConfig($page->kind);
        if (isset($kindConfig['has_title']) && !$kindConfig['has_title']) {
            $hasTitle = false;
        }

        if ($hasTitle) {
            $page->title = $prefix . $page->title;
        } else {
            $page->content->content = $prefix . $page->content->content;
            $page->content->rawBody = $prefix . $page->content->rawBody;
        }
    }

    /**
     * Recursively scans a directory for markdown content files.
     * Parses valid markdown files into Page objects and adds them to the collection.
     * Skips system directories (e.g., app, vendor, output dirs).
     *
     * @param string $dir The directory path to scan.
     * @return void
     */
    public function scan(string $dir): void
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
                $entry !== "."
                && $entry !== ".."
                && substr($entry, 0, 1) !== "_"
                && substr($entry, 0, 1) !== "."
            ) {
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path)) {
                    if ($entry === 'intro.md') {
                        continue;
                    }
                    $page = $this->parser->parse($path);
                    if ($page) {
                        $this->pages->add($page);
                    }
                } elseif (is_dir($path)) {
                    $themeDir = $this->site->paths->themeDir ?? 'theme';
                    if (
                        strpos($path, DIRECTORY_SEPARATOR . "app") === false
                        && strpos($path, DIRECTORY_SEPARATOR . "bootstrap") === false
                        && strpos($path, DIRECTORY_SEPARATOR . "vendor") === false
                        && strpos($path, DIRECTORY_SEPARATOR . "resources") === false
                        && strpos($path, DIRECTORY_SEPARATOR . $themeDir) === false
                        && strpos($path, DIRECTORY_SEPARATOR . "theme") === false
                        && strpos($path, DIRECTORY_SEPARATOR . "data") === false
                        && strpos($path, DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml) === false
                        && strpos($path, DIRECTORY_SEPARATOR . $this->site->paths->outputDirGemini) === false
                        && strpos($path, DIRECTORY_SEPARATOR . $this->site->paths->outputDirGopher) === false
                        && strpos($path, DIRECTORY_SEPARATOR . $this->site->paths->outputDirMedia) === false
                    ) {
                        $this->scan($path);
                    }
                }
            }
        }
    }

    /**
     * Iterates over all parsed pages and triggers the generation of HTML, 
     * Gemini, and Gopher files for each. Also generates sitemaps and indexes.
     *
     * @return void
     */
    public function generateHTMLFiles(): void
    {
        $pagesByKind = [];
        foreach ($this->pages as $page) {
            $pagesByKind[$page->kind][] = $page;
            $this->createHTMLFile($page);
            $this->createGeminiFile($page);
            $this->createGopherFile($page);
        }

        // Generate Sitemap
        $this->compileSitemap();

        $kinds = $this->site->config['kinds'] ?? [];
        foreach ($kinds as $kind => $config) {
            $pagesForKind = $pagesByKind[$kind] ?? [];
            if (empty($pagesForKind)) {
                continue;
            }
            if (isset($config['show_in_menu']) && !$config['show_in_menu']) {
                continue;
            }

            $displayMode = $config['display_mode'] ?? 'default';

            if ($displayMode === 'full_content') {
                $this->compileTimelineIndexes($kind, $pagesForKind);
            } else {
                $this->compileSectionIndexes($kind, $pagesForKind);
            }
        }
    }

    /**
     * Renders a single Page object into an HTML file using the configured theme.
     * Handles slug resolution, metadata extraction, and shortlink generation.
     *
     * @param \Indieinabox\Page $page The page to render.
     * @return void
     */
    private function createHTMLFile(Page $page): void
    {
        $base = $this->site->paths->baseDir;
        $site = $this->site;
        
        // Generate shortlink if enabled
        if (!empty($site->config['shortlink']['enabled'])) {
            $shortlinkManager = new \Indieinabox\ShortlinkManager();
            $fqdn = rtrim($site->metadata->fqdn ?? 'http://localhost', '/');
            $isDev = isset($site->options->dev) && $site->options->dev;
            $page->shortlink = $shortlinkManager->getShortlink($page, $fqdn, $site->config['shortlink'], $isDev);
        }

        // Expose $p, $pages, $site, $langLinks, $headerLinks and $footerLinks to the global scope for view template compatibility
        global $p, $site, $pages, $langLinks, $headerLinks, $footerLinks;
        $p = $page;
        $pages = $this->pages;
        $langLinks = $this->getLanguageLinks($page);
        
        $menuLinks = $this->getMenuLinks($page);
        $headerLinks = $menuLinks['header'];
        $footerLinks = $menuLinks['footer'];

        if (in_array("draft", $page->metadata->tags)) {
            return;
        }

        $destination = str_replace("/", DIRECTORY_SEPARATOR, $page->slug);
        $destination = trim($destination, DIRECTORY_SEPARATOR);
        $destination = preg_replace(
            "/^" . preg_quote($this->site->paths->contentDir, '/') . "/",
            "",
            $destination
        );
        $destination = trim($destination, DIRECTORY_SEPARATOR);

        $outDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml;

        if (str_ends_with($destination, '.html')) {
            $dir = dirname($outDir . DIRECTORY_SEPARATOR . $destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $destinationFile = $outDir . DIRECTORY_SEPARATOR . $destination;
        } else {
            $destDir = $destination === '' ? $outDir : $outDir . DIRECTORY_SEPARATOR . $destination;
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            $destinationFile = $destDir . DIRECTORY_SEPARATOR . "index.html";
        }
        $destinationFile = preg_replace('#([^:])(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', '$1' . DIRECTORY_SEPARATOR, $destinationFile);
        $destinationFile = preg_replace('#^(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', DIRECTORY_SEPARATOR, $destinationFile);
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        
        // True incremental build: skip if destination is newer than source and theme (only in dev mode)
        $skipGeneration = false;
        if (isset($this->site->options->dev) && $this->site->options->dev) {
            $mdMtime = ($page->filepath && file_exists($page->filepath)) ? filemtime($page->filepath) : 0;
            static $maxThemeMtime = null;
            if ($maxThemeMtime === null) {
                $maxThemeMtime = 0;
                $fullThemeDir = $base . DIRECTORY_SEPARATOR . $themeDir;
                if (is_dir($fullThemeDir)) {
                    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fullThemeDir));
                    foreach ($files as $file) {
                        if ($file->isFile()) {
                            $maxThemeMtime = max($maxThemeMtime, $file->getMTime());
                        }
                    }
                }
            }
            $maxMtime = max($mdMtime, $maxThemeMtime);
            if (file_exists($destinationFile) && filemtime($destinationFile) >= $maxMtime) {
                $skipGeneration = true;
            }
        }

        // Build interactions pages if there are any interactions
        $likes = \Indieinabox\Helper::getInteractions($page, 'like');
        $reposts = \Indieinabox\Helper::getInteractions($page, 'repost');
        $replies = \Indieinabox\Helper::getInteractions($page, 'reply');

        if (!$skipGeneration) {
            if (str_ends_with($destinationFile, '.html')) {
                echo "Built " . str_replace($outDir . DIRECTORY_SEPARATOR, '', $destinationFile) . "\n";
            } else {
                echo "Built " . $page->slug . "index.html" . "\n";
            }
            ob_start();
            // phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative
            ThemeManager::loadView(
                $base . DIRECTORY_SEPARATOR . $themeDir . "/views/" . $page->metadata->layout . ".php",
                get_defined_vars()
            );
            $fileContent = ob_get_clean();

            if (isset($this->site->options->htmlpostprocessing)) {
                if ($this->site->options->htmlpostprocessing == "beautify" || $this->site->options->dev) {
                    $fileContent = Helper::beautifyhtml($fileContent);
                }
                if ($this->site->options->htmlpostprocessing == "minify" && !$this->site->options->dev) {
                    $fileContent = Helper::minifyhtml($fileContent);
                }
            }

            if (str_starts_with($destinationFile, 'vfs:/root')) {
                var_dump("DEST_FILE: " . $destinationFile);
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            }
            file_put_contents($destinationFile, $fileContent);

            // Build ActivityPub JSON representation
            $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
            $actorId = $fqdn . '/actor';
            $postUrl = $fqdn . '/' . $destination;
            if (str_ends_with($postUrl, '.html')) {
                $postUrl = substr($postUrl, 0, -5);
            }
            $metadataArray = (array) $page->metadata;
            $title = $page->metadata->title === 'Untitled' ? null : $page->metadata->title;
            $apObject = \Indieinabox\ActivityPubHandler::buildObjectForPageArray($postUrl, $actorId, $fqdn, $page->content->content, $title, $metadataArray);
            
            $jsonDestination = dirname($destinationFile) . DIRECTORY_SEPARATOR . 'index.json';
            if (str_ends_with($destinationFile, '.html') && basename($destinationFile) !== 'index.html') {
                $jsonDestination = substr($destinationFile, 0, -5) . '.json';
            }
            file_put_contents($jsonDestination, json_encode($apObject, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            // Build interactions pages if there are any interactions
            $likes = \Indieinabox\Helper::getInteractions($page, 'like');
            $reposts = \Indieinabox\Helper::getInteractions($page, 'repost');
            $replies = \Indieinabox\Helper::getInteractions($page, 'reply');

            if (count($likes) > 0 || count($reposts) > 0 || count($replies) > 0) {
                $interactionsDir = dirname($destinationFile) . DIRECTORY_SEPARATOR . 'interactions';
                if (!is_dir($interactionsDir)) {
                    mkdir($interactionsDir, 0777, true);
                }
                $interactionsFile = $interactionsDir . DIRECTORY_SEPARATOR . 'index.html';
                $interactionsPage = clone $page;
                $interactionsPage->relpath .= '../';
                
                ob_start();
                $viewVars = get_defined_vars();
                $viewVars['page'] = $interactionsPage;
                ThemeManager::loadView(
                    $base . DIRECTORY_SEPARATOR . $themeDir . "/views/interactions_page.php",
                    $viewVars
                );
                $interactionsContent = ob_get_clean();
                
                if (isset($this->site->options->htmlpostprocessing)) {
                    if ($this->site->options->htmlpostprocessing == "beautify" || $this->site->options->dev) {
                        $interactionsContent = Helper::beautifyhtml($interactionsContent);
                    }
                    if ($this->site->options->htmlpostprocessing == "minify" && !$this->site->options->dev) {
                        $interactionsContent = Helper::minifyhtml($interactionsContent);
                    }
                }
                file_put_contents($interactionsFile, $interactionsContent);
            }
        }
        
        // Add to manifest
        \Indieinabox\SiteBuilder::addManifest($destinationFile);
        
        $jsonDestination = dirname($destinationFile) . DIRECTORY_SEPARATOR . 'index.json';
        if (str_ends_with($destinationFile, '.html') && basename($destinationFile) !== 'index.html') {
            $jsonDestination = substr($destinationFile, 0, -5) . '.json';
        }
        if (file_exists($jsonDestination)) {
            \Indieinabox\SiteBuilder::addManifest($jsonDestination);
        }

        $interactionsFile = dirname($destinationFile) . DIRECTORY_SEPARATOR . 'interactions' . DIRECTORY_SEPARATOR . 'index.html';
        if (file_exists($interactionsFile)) {
            \Indieinabox\SiteBuilder::addManifest($interactionsFile);
            \Indieinabox\SiteBuilder::addManifest(dirname($interactionsFile));
        }

        foreach ($replies as $replyItem) {
            $hash = md5($replyItem['url']);
            $replyDir = dirname($destinationFile) . DIRECTORY_SEPARATOR . 'reply' . DIRECTORY_SEPARATOR . $hash;
            if (!is_dir($replyDir)) {
                mkdir($replyDir, 0777, true);
            }
            $replyFile = $replyDir . DIRECTORY_SEPARATOR . 'index.html';
            $replyPage = clone $page;
            $replyPage->relpath .= '../../';
            
            ob_start();
            $viewVars = array_merge(get_defined_vars(), ['reply' => $replyItem]);
            $viewVars['page'] = $replyPage;
            ThemeManager::loadView(
                $base . DIRECTORY_SEPARATOR . $themeDir . "/views/interaction_reply.php",
                $viewVars
            );
            $replyContent = ob_get_clean();
            
            if (isset($this->site->options->htmlpostprocessing)) {
                if ($this->site->options->htmlpostprocessing == "beautify" || $this->site->options->dev) {
                    $replyContent = Helper::beautifyhtml($replyContent);
                }
                if ($this->site->options->htmlpostprocessing == "minify" && !$this->site->options->dev) {
                    $replyContent = Helper::minifyhtml($replyContent);
                }
            }
            file_put_contents($replyFile, $replyContent);
            \Indieinabox\SiteBuilder::addManifest($replyFile);
        }
    }

    /**
     * Renders standard RSS and Atom feeds for the site.
     * Uses the configured theme's feed view file if available.
     *
     * @return void
     */
    public function generateFeed(): void
    {
        $base = $this->site->paths->baseDir;
        $site = $this->site;
        $pages = $this->pages;
        // Expose to global scope for view template compatibility
        global $pages, $site;

        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $file = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views"
            . DIRECTORY_SEPARATOR . "feed" . ".php";
        if (file_exists($file) && is_readable($file)) {
            ThemeManager::loadView($file, get_defined_vars());
        }
    }

    /**
     * Copies theme assets (e.g., CSS, JS, fonts) from the theme directory to the public output.
     *
     * @param string $dir The source directory containing theme assets.
     * @return void
     */
    public function copyAssets(string $dir): void
    {
        $base = $this->site->paths->baseDir;

        if (!is_dir($dir) && !class_exists('\\DefaultTheme')) {
            return;
        }

        ThemeManager::copyViewAssets($dir, $base, $this->site->paths->outputDirHtml);
    }

    /**
     * Copies general static files from the given directory to the output HTML directory.
     * Also triggers the injection of live.js for hot-reloading if dev mode is enabled.
     *
     * @param string $dir The source directory containing static files.
     * @return bool True if copy was successful or no theme exists, false otherwise.
     */
    public function copyStatic(string $dir): bool
    {
        $base = $this->site->paths->baseDir;

        if (!is_dir($dir) && !class_exists('\\DefaultTheme')) {
            return false;
        }

        echo "Copying static files\n";
        ThemeManager::copyStaticFiles($dir, $base, $this->site->paths->outputDirHtml);

        if ($this->site->options->dev) {
            $this->copyLiveJsFile($base);
        }

        return true;
    }



    /**
     * Copies the live.js script for live reloading during development.
     *
     * @param string $base The base installation directory.
     * @return void
     */
    private function copyLiveJsFile(string $base): void
    {
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $jsDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml . DIRECTORY_SEPARATOR . "js";

        if (!is_dir($jsDir)) {
            mkdir($jsDir, 0777, true);
        }

        $liveJsFile = \Indieinabox\Database::$dataDir . DIRECTORY_SEPARATOR . 'live.js';
        if (file_exists($liveJsFile)) {
            echo "Copying static files: from $liveJsFile to $jsDir/live.js\n";
            $destFile = $jsDir . "/live.js";
            $success = copy($liveJsFile, $destFile);
            if (!$success) {
                echo "Failed to copy $liveJsFile\n";
            } else {
                \Indieinabox\SiteBuilder::addManifest($destFile);
            }
        } else {
            echo "File does not exist (skip copying): $liveJsFile\n";
        }
    }

    /**
     * Renders a page into the Gemini protocol format (.gmi) and writes it to the gemini output directory.
     * Parses the markdown AST and formats it according to Gemini conventions.
     *
     * @param \Indieinabox\Page $page The page to render.
     * @return void
     */
    private function createGeminiFile(Page $page): void
    {
        if (in_array("draft", $page->metadata->tags)) {
            return;
        }

        $base = $this->site->paths->baseDir;
        $destination = str_replace("/", DIRECTORY_SEPARATOR, $page->slug);
        $destination = trim($destination, DIRECTORY_SEPARATOR);
        $destination = preg_replace(
            "/^" . preg_quote($this->site->paths->contentDir, '/') . "/",
            "",
            $destination
        );
        $destination = trim($destination, DIRECTORY_SEPARATOR);

        $outDirGemini = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGemini;
        if (str_ends_with($destination, '.html') || str_ends_with($destination, '.htm')) {
            $ext = str_ends_with($destination, '.html') ? '.html' : '.htm';
            $dir = dirname($outDirGemini . DIRECTORY_SEPARATOR . $destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $destinationFile = $outDirGemini
                . DIRECTORY_SEPARATOR
                . str_replace($ext, '.gmi', $destination);
        } else {
            $destDir = $destination === '' ? $outDirGemini : $outDirGemini . DIRECTORY_SEPARATOR . $destination;
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            $destinationFile = $destDir . DIRECTORY_SEPARATOR . "index.gmi";
        }
        $destinationFile = preg_replace('#([^:])(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', '$1' . DIRECTORY_SEPARATOR, $destinationFile);
        $destinationFile = preg_replace('#^(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', DIRECTORY_SEPARATOR, $destinationFile);
        // True incremental build: skip if destination is newer than source (only in dev mode)
        $skipGeneration = false;
        if (isset($this->site->options->dev) && $this->site->options->dev) {
            $mdMtime = ($page->filepath && file_exists($page->filepath)) ? filemtime($page->filepath) : 0;
            if (file_exists($destinationFile) && filemtime($destinationFile) >= $mdMtime) {
                $skipGeneration = true;
            }
        }

        if (!$skipGeneration) {
            echo "Built " . str_replace($outDirGemini . DIRECTORY_SEPARATOR, '', $destinationFile) . "\n";
            $astParser = new ASTParser();
            $gemtextRenderer = new GemtextRenderer($page);
            $rawBody = $page->rawBody ?? '';
            $ast = $astParser->parse($rawBody);
            $title = $page->title;

            $dateStr = $page->localizeddate;
            $author = $this->site->metadata->author;

            $gmiContent = "# {$title}\n";
            if ($dateStr) {
                $gmiContent .= "Published: {$dateStr}";
                if ($author) {
                    $gmiContent .= " by {$author}";
                }
                $gmiContent .= "\n";
            }
            $gmiContent .= "\n";

            $gmiContent .= $gemtextRenderer->render($ast);
            $gmiContent .= "\n=> / Back to Home\n";

            file_put_contents($destinationFile, $gmiContent);
        }
        \Indieinabox\SiteBuilder::addManifest($destinationFile);
    }

    /**
     * Renders a page into the Gopher protocol format (gophermap) and writes it to the gopher output directory.
     * Formats links and metadata according to RFC 1436.
     *
     * @param \Indieinabox\Page $page The page to render.
     * @return void
     */
    private function createGopherFile(Page $page): void
    {
        if (in_array("draft", $page->metadata->tags)) {
            return;
        }

        $base = $this->site->paths->baseDir;
        $destination = str_replace("/", DIRECTORY_SEPARATOR, $page->slug);
        $destination = trim($destination, DIRECTORY_SEPARATOR);
        $destination = preg_replace(
            "/^" . preg_quote($this->site->paths->contentDir, '/') . "/",
            "",
            $destination
        );
        $destination = trim($destination, DIRECTORY_SEPARATOR);

        $outDirGopher = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGopher;
        if (str_ends_with($destination, '.html') || str_ends_with($destination, '.htm')) {
            $ext = str_ends_with($destination, '.html') ? '.html' : '.htm';
            $dir = dirname($outDirGopher . DIRECTORY_SEPARATOR . $destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $destinationFile = $outDirGopher . DIRECTORY_SEPARATOR . dirname($destination)
                . DIRECTORY_SEPARATOR . basename($destination, $ext) . '.gophermap';
        } else {
            $destDir = $destination === '' ? $outDirGopher : $outDirGopher . DIRECTORY_SEPARATOR . $destination;
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            $destinationFile = $destDir . DIRECTORY_SEPARATOR . "gophermap";
        }
        $destinationFile = preg_replace('#([^:])(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', '$1' . DIRECTORY_SEPARATOR, $destinationFile);
        $destinationFile = preg_replace('#^(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', DIRECTORY_SEPARATOR, $destinationFile);

        // True incremental build: skip if destination is newer than source (only in dev mode)
        $skipGeneration = false;
        if (isset($this->site->options->dev) && $this->site->options->dev) {
            $mdMtime = ($page->filepath && file_exists($page->filepath)) ? filemtime($page->filepath) : 0;
            if (file_exists($destinationFile) && filemtime($destinationFile) >= $mdMtime) {
                $skipGeneration = true;
            }
        }

        if (!$skipGeneration) {
            echo "Built " . str_replace($outDirGopher . DIRECTORY_SEPARATOR, '', $destinationFile) . "\n";

            $host = 'gopher.example.com';
        if ($this->site->metadata->fqdn) {
            $parsedUrl = parse_url($this->site->metadata->fqdn);
            $host = $parsedUrl['host'] ?? $host;
        }

            $astParser = new ASTParser();
            $gophermapRenderer = new GophermapRenderer($host, 70, $page);

            $rawBody = $page->rawBody ?? '';
            $ast = $astParser->parse($rawBody);

            $title = $page->title;
            $dateStr = $page->localizeddate;
            $author = $this->site->metadata->author;

            $formatInfo = function (string $text): string {
                return "i{$text}\t\t(null)\t0\r\n";
            };

        $gopherContent = $formatInfo("=== {$title} ===");
        if ($dateStr) {
            $meta = "Published: {$dateStr}";
            if ($author) {
                $meta .= " by {$author}";
            }
            $gopherContent .= $formatInfo($meta);
        }
        $gopherContent .= $formatInfo("");

            $gopherContent .= $gophermapRenderer->render($ast);
            $gopherContent .= $formatInfo("");
            $gopherContent .= "1Back to Home\t/\t{$host}\t70\r\n";

            file_put_contents($destinationFile, $gopherContent);
        }
        \Indieinabox\SiteBuilder::addManifest($destinationFile);
    }

    /**
     * Generates Twtxt (Microblogging) feed files for the site and each language.
     * Extracts content specific to the Twtxt format (max 140 chars or full content).
     *
     * @return void
     */
    public function generateTwtxt(): void
    {
        $base = $this->site->paths->baseDir;
        $outDirHtml = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml;
        $outDirGemini = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGemini;
        $outDirGopher = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGopher;
        if (!is_dir($outDirHtml)) {
            mkdir($outDirHtml, 0777, true);
        }
        if (!is_dir($outDirGemini)) {
            mkdir($outDirGemini, 0777, true);
        }
        if (!is_dir($outDirGopher)) {
            mkdir($outDirGopher, 0777, true);
        }

        // 1. Generate local feeds: public/twtxt.txt, rss.xml, atom.xml (and for each language)
        $twtxtManager = new \Indieinabox\Twtxt\TwtxtManager();
        $feedManager = new \Indieinabox\Feeds\FeedManager();
        $defaultLang = $this->site->localization->defaultLang ?? 'en';

        $pagesByLang = [];
        foreach ($this->pages as $page) {
            $lang = $page->lang ?? $defaultLang;
            if (!isset($pagesByLang[$lang])) {
                $pagesByLang[$lang] = [];
            }
            $pagesByLang[$lang][] = $page;
        }

        echo "Generating twtxt.txt feeds...\n";
        foreach ($pagesByLang as $lang => $langPages) {
            $langDirHtml = $outDirHtml;
            $langDirGemini = $outDirGemini;
            $langDirGopher = $outDirGopher;
            if ($lang !== $defaultLang) {
                $langDirHtml .= DIRECTORY_SEPARATOR . $lang;
                $langDirGemini .= DIRECTORY_SEPARATOR . $lang;
                $langDirGopher .= DIRECTORY_SEPARATOR . $lang;
                if (!is_dir($langDirHtml)) mkdir($langDirHtml, 0777, true);
                if (!is_dir($langDirGemini)) mkdir($langDirGemini, 0777, true);
                if (!is_dir($langDirGopher)) mkdir($langDirGopher, 0777, true);
            }

            $feedFile = $langDirHtml . DIRECTORY_SEPARATOR . 'twtxt.txt';
            $twtxtManager->generateFeed(
                $langPages,
                $feedFile,
                $this->site->metadata->fqdn,
                $this->site->twtxt
            );
            
            // Copy to other formats
            $geminiTwtxt = $langDirGemini . DIRECTORY_SEPARATOR . 'twtxt.txt';
            $gopherTwtxt = $langDirGopher . DIRECTORY_SEPARATOR . 'twtxt.txt';
            copy($feedFile, $geminiTwtxt);
            copy($feedFile, $gopherTwtxt);
            
            \Indieinabox\SiteBuilder::addManifest($feedFile);
            \Indieinabox\SiteBuilder::addManifest($geminiTwtxt);
            \Indieinabox\SiteBuilder::addManifest($gopherTwtxt);

            // Generate RSS and Atom
            $rssFile = $langDirHtml . DIRECTORY_SEPARATOR . 'rss.xml';
            $atomFile = $langDirHtml . DIRECTORY_SEPARATOR . 'atom.xml';
            
            $feedLimit = $this->site->options->feed_limit ?? 20;
            
            $feedManager->generateRss(
                $langPages,
                $rssFile,
                $this->site->metadata->fqdn,
                $this->site->metadata,
                $feedLimit
            );
            
            $feedManager->generateAtom(
                $langPages,
                $atomFile,
                $this->site->metadata->fqdn,
                $this->site->metadata,
                $feedLimit
            );
            
            \Indieinabox\SiteBuilder::addManifest($rssFile);
            \Indieinabox\SiteBuilder::addManifest($atomFile);
        }

        // 2. Fetch aggregated timeline & mentions if subscriptions/hubs are configured
        echo "Fetching twtxt timeline and mentions...\n";
        $cacheDir = $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'twtxt_cache';

        $timelineEntries = [];
        $mentionEntries = [];

        if (!empty($this->site->twtxt->following)) {
            $timelineEntries = $twtxtManager->fetchTimeline($this->site->twtxt->following, $cacheDir, false);
        }
        if (!empty($this->site->twtxt->hubs)) {
            $mentionEntries = $twtxtManager->fetchHubMentions($this->site->twtxt->hubs, $this->site->metadata->fqdn, $cacheDir, false);
        }

        // 3. Compile the static timeline page: public/timeline/index.html
        echo "Compiling timeline static page...\n";
        $timelinePage = Page::fromArray([
            'title' => 'Timeline',
            'layout' => 'timeline',
            'slug' => 'timeline/',
            'date' => time(),
            'content' => '',
            'originalcontent' => ''
        ]);

        // Expose timeline & mentions globally for timeline.php view template
        global $timeline, $mentions;
        $timeline = $timelineEntries;
        $mentions = $mentionEntries;

        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $layoutFile = $base . DIRECTORY_SEPARATOR . $themeDir
            . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'timeline.php';
        if (file_exists($layoutFile) && is_readable($layoutFile)) {
            $this->createHTMLFile($timelinePage);
        } else {
            echo "Skipping timeline static page compilation: timeline layout not found.\n";
        }
    }

    /**
     * @return array<string, string>
     */
    private function getLanguageLinks(Page $page): array
    {
        global $urltranslations;
        if (!is_array($urltranslations)) {
            $urltranslations = [];
        }

        $langs = $this->site->localization->lang;
        $defaultLang = $this->site->localization->defaultLang ?? 'en';
        $prettylinks = $this->site->options->prettylinks ?? true;

        $slug = $page->slug;
        $parts = explode('/', trim($slug, '/'));
        if (in_array($parts[0], $langs, true) && $parts[0] !== $defaultLang) {
            array_shift($parts);
        }

        // Get localized folder names of all kinds in all active languages
        $kindFolders = [];
        if (!empty($this->site->config['kinds'])) {
            foreach ($this->site->config['kinds'] as $k => $conf) {
                foreach ($langs as $l) {
                    $kindFolders[] = \Indieinabox\Helper::getKindFolder($k, $l);
                }
            }
        }
        // Also legacy folder names for backup
        global $kindspath;
        if ($kindspath === null) {
            $kindspath = \Indieinabox\Database::getSetting('kindspath', []);
        }
        if (!empty($kindspath)) {
            foreach ($kindspath as $key => $values) {
                foreach ($values as $val) {
                    $kindFolders[] = $val;
                }
            }
        }
        $kindFolders = array_unique($kindFolders);

        if (isset($parts[0]) && in_array($parts[0], $kindFolders, true)) {
            array_shift($parts);
        }
        $nick = end($parts);
        if ($nick === false) {
            $nick = '';
        }
        // Strip .html extension from nick when prettylinks is off to avoid double .html in links
        if (!$prettylinks && str_ends_with($nick, '.html')) {
            $nick = substr($nick, 0, -5);
        }

        $translationGroup = null;
        $baseKey = null;
        foreach ($urltranslations as $key => $langsList) {
            if ($nick === $key) {
                $translationGroup = $langsList;
                $baseKey = $key;
                break;
            }
            foreach ($langsList as $lang => $translatedNick) {
                if ($nick === $translatedNick) {
                    $translationGroup = $langsList;
                    $baseKey = $key;
                    break 2;
                }
            }
        }

        // If no translation mapping is found, treat the current $nick as the baseKey
        if ($baseKey === null) {
            $baseKey = $nick;
        }

        $links = [];
        foreach ($langs as $l) {
            if ($l === $defaultLang) {
                $links[$l] = '/';
            } else {
                $links[$l] = '/' . $l . '/';
            }
        }

        {
            $kind = $page->kind;

        foreach ($langs as $l) {
            $folder = '';
            if ($kind !== 'generic' && $kind !== 'page' && $kind !== 'home') {
                $folder = $this->getKindFolder($kind, $l);
            }

            // Get the translated slug part, fallback to baseKey (which is the english/default nick)
            $localizedSlugPart = $baseKey;
            if ($translationGroup !== null) {
                $localizedSlugPart = ($l === $defaultLang) ? $baseKey : ($translationGroup[$l] ?? $baseKey);
            }

            // Force empty slug part for the home page so it points to the language root
            if ($kind === 'home') {
                $localizedSlugPart = '';
            }

            if ($prettylinks) {
                if ($l === $defaultLang) {
                    $links[$l] = '/' . ($folder ? $folder . '/' : '')
                        . ($localizedSlugPart !== '' ? $localizedSlugPart . '/' : '');
                } else {
                    $links[$l] = '/' . $l . '/' . ($folder ? $folder . '/' : '')
                        . ($localizedSlugPart !== '' ? $localizedSlugPart . '/' : '');
                }
            } else {
                if ($l === $defaultLang) {
                    $links[$l] = '/' . ($folder ? $folder . '/' : '')
                        . ($localizedSlugPart !== '' ? $localizedSlugPart . '.html' : 'index.html');
                } else {
                    $links[$l] = '/' . $l . '/' . ($folder ? $folder . '/' : '')
                        . ($localizedSlugPart !== '' ? $localizedSlugPart . '.html' : 'index.html');
                }
            }
        }
        }

        foreach ($links as $lang => $url) {
            $url = '/' . ltrim(preg_replace('#/+#', '/', $url), '/');
            
            $exists = false;
            if ($kind === 'home' || $kind === 'generic') {
                $exists = true; // Home and generic index are always accessible
            } else {
                $targetNick = $baseKey;
                if ($translationGroup !== null) {
                    $targetNick = ($lang === $defaultLang) ? $baseKey : ($translationGroup[$lang] ?? $baseKey);
                }
                foreach ($this->pages as $p) {
                    $pLang = $p->lang ?? $defaultLang;
                    if ($pLang === $lang && $p->kind === $kind && $p->nick === $targetNick) {
                        $exists = true;
                        break;
                    }
                }
            }
            
            if (!$exists) {
                // fallback to language root (Home)
                $url = $lang === $defaultLang ? '/' : '/' . $lang . '/';
            }
            
            $links[$lang] = $url;
        }

        return $links;
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private function getMenuLinks(Page $page): array
    {
        $headerLinks = [];
        $footerLinks = [];
        
        $lang = $page->lang ?? ($this->site->localization->defaultLang ?? 'en');
        $defaultLang = $this->site->localization->defaultLang ?? 'en';
        $langPrefix = ($lang === $defaultLang) ? '' : $lang . '/';
        $prettylinks = $this->site->options->prettylinks ?? true;

        // 1. Post kinds defined in config (default to footer)
        if (!empty($this->site->config['kinds'])) {
            foreach ($this->site->config['kinds'] as $k => $conf) {
                if (isset($conf['show_on_home']) && !$conf['show_on_home'] && $k !== 'garden' && $k !== 'jardim') {
                    // Do not skip here just for home, since this is for menu
                }
                
                // Hide if explicitly configured
                if (isset($conf['show_in_menu']) && !$conf['show_in_menu']) {
                    continue;
                }
                
                // Check if there are any pages for this kind
                $hasPages = false;
                foreach ($this->pages as $p) {
                    $pLang = $p->lang ?? $defaultLang;
                    if ($p->kind === $k && $pLang === $lang) {
                        $hasPages = true;
                        break;
                    }
                }
                
                if (!$hasPages) {
                    continue;
                }
                
                $folder = $this->getKindFolder($k, $lang);
                if ($prettylinks) {
                    $url = $page->relpath . $langPrefix . $folder . '/';
                } else {
                    $url = $page->relpath . $langPrefix . $folder . '/index.html';
                }
                $label = \Indieinabox\Helper::kindLabel($k, $lang);
                $footerLinks[] = ['url' => $url, 'label' => $label, 'order' => PHP_INT_MAX];
            }
        }

        // 2. MD files with kind: page
        foreach ($this->pages as $p) {
            $pLang = $p->lang ?? $defaultLang;
            
            $menuVal = $p->metadata->menu ?? 'footer';
            if ($menuVal === 'hide') {
                continue;
            }
            
            if ($pLang === $lang && $p->kind === 'page') {
                $url = $page->relpath . ltrim($p->slug, '/');
                $label = $p->title;
                $order = $p->metadata->menu_order ?? PHP_INT_MAX;
                
                $linkItem = ['url' => $url, 'label' => $label, 'order' => $order];
                
                if ($menuVal === 'header') {
                    $headerLinks[] = $linkItem;
                } elseif ($menuVal === 'both') {
                    $headerLinks[] = $linkItem;
                    $footerLinks[] = $linkItem;
                } else {
                    // 'footer' or any omitted/default value
                    $footerLinks[] = $linkItem;
                }
            }
        }

        // 3. Sort links: numbered first, then alphabetically
        $sortFn = function($a, $b) {
            $orderA = $a['order'] ?? PHP_INT_MAX;
            $orderB = $b['order'] ?? PHP_INT_MAX;
            
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }
            
            return strcasecmp($a['label'] ?? '', $b['label'] ?? '');
        };
        
        usort($headerLinks, $sortFn);
        usort($footerLinks, $sortFn);

        // Strip order key to match original shape
        foreach ($headerLinks as &$link) {
            unset($link['order']);
        }
        foreach ($footerLinks as &$link) {
            unset($link['order']);
        }

        return [
            'header' => $headerLinks,
            'footer' => $footerLinks
        ];
    }

    /**
     * Resolves the output folder name for a specific content kind and language.
     * Checks localized configuration, default language fallbacks, and raw slugs.
     *
     * @param string $kind The internal content kind slug.
     * @param string $lang The target language.
     * @return string The resolved and slugified folder name.
     */
    private function getKindFolder(string $kind, string $lang): string
    {
        return \Indieinabox\Helper::getKindFolder($kind, $lang);
    }

    /**
     * @param \Indieinabox\Page[] $pages
     */
    private function compileTimelineIndexes(string $targetKind, array $pages): void
    {
        $grouped = [];
        foreach ($pages as $p) {
            if (basename($p->filepath) === 'intro.md') {
                continue;
            }

            $lang = $p->lang ?? 'en';
            $date = $p->date;
            $yearMonth = $date->format('Y-m');

            $grouped[$lang][$yearMonth][] = $p;
        }

        foreach ($grouped as $lang => &$months) {
            krsort($months);
            foreach ($months as $yearMonth => &$monthPages) {
                usort($monthPages, function ($a, $b) {
                    $timeA = $a->date->getTimestamp();
                    $timeB = $b->date->getTimestamp();
                    return $timeB <=> $timeA;
                });
            }
            unset($monthPages);
        }
        unset($months);

        $base = $this->site->paths->baseDir;
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $summaryFile = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views"
            . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "summary.php";

        foreach ($grouped as $lang => $months) {
            /** @var \Indieinabox\Page[] $allPagesForLang */
            $allPagesForLang = [];
            $titleBase = \Indieinabox\Helper::kindLabel($targetKind, $lang);

            foreach ($months as $yearMonth => $monthPages) {
                $monthSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                    . $this->getKindFolder($targetKind, $lang) . '/' . $yearMonth . '/';
                $monthPage = Page::fromArray([
                    'title' => $titleBase . " - " . $yearMonth,
                    'layout' => 'index_page',
                    'slug' => $monthSlug,
                    'date' => new \DateTime($yearMonth . '-01'),
                    'content' => '',
                    'rawBody' => '',
                    'lang' => $lang,
                    'kind' => $targetKind
                ]);

                $monthContent = '';
                $monthRaw = '';
                foreach ($monthPages as $idx => $p) {
                    if ($idx > 0) {
                        $monthContent .= "\n<hr class=\"divisor-bloco\">\n";
                        $monthRaw .= "\n\n---\n\n";
                    }

                    if (file_exists($summaryFile)) {
                        ob_start();
                        global $site;
                        $site = $this->site;
                        $page = clone $p;
                        $page->relpath = $monthPage->relpath;
                        ThemeManager::loadView($summaryFile, get_defined_vars());
                        $monthContent .= ob_get_clean();
                    } else {
                        $monthContent .= $p->content;
                    }
                    $monthRaw .= $p->rawBody;
                }

                $monthPage->content->content = $monthContent;
                $monthPage->content->rawBody = $monthRaw;

                $allPagesForLang = array_merge($allPagesForLang, $monthPages);

                $this->createHTMLFile($monthPage);
                $this->createGeminiFile($monthPage);
                $this->createGopherFile($monthPage);
            }

            $indexSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                . $this->getKindFolder($targetKind, $lang) . '/';
            $indexPage = Page::fromArray([
                'title' => $titleBase,
                'layout' => 'index_page',
                'slug' => $indexSlug,
                'date' => time(),
                'content' => '',
                'rawBody' => '',
                'lang' => $lang,
                'kind' => $targetKind
            ]);

            $indexContent = '';
            $indexRaw = '';
            foreach ($allPagesForLang as $idx => $p) {
                if ($idx > 0) {
                    $indexContent .= "\n<hr class=\"divisor-bloco\">\n";
                    $indexRaw .= "\n\n---\n\n";
                }

                if (file_exists($summaryFile)) {
                    ob_start();
                    global $site;
                    $site = $this->site;
                    $page = clone $p;
                    $page->relpath = $indexPage->relpath;
                    ThemeManager::loadView($summaryFile, get_defined_vars());
                    $indexContent .= ob_get_clean();
                } else {
                    $indexContent .= $p->content;
                }
                $indexRaw .= $p->rawBody;
            }

            $indexPage->content->content = $indexContent;
            $indexPage->content->rawBody = $indexRaw;

            $this->createHTMLFile($indexPage);
            $this->createGeminiFile($indexPage);
            $this->createGopherFile($indexPage);
        }
    }

    /**
     * Generates a sitemap.xml file encompassing all non-draft pages.
     * Creates standard sitemaps for the HTML site to assist search engine indexing.
     *
     * @return void
     */
    private function compileSitemap(): void
    {
        $defaultLang = $this->site->localization->defaultLang ?? 'en';
        $indexSlugConfig = $this->site->config['index_slug'] ?? 'index';

        foreach ($this->site->localization->lang as $lang) {
            $prettylinks = $this->site->options->prettylinks ?? true;
            if ($prettylinks) {
                $sitemapSlug = ($lang === $defaultLang ? '' : $lang . '/') . $indexSlugConfig . '/';
            } else {
                $sitemapSlug = ($lang === $defaultLang ? '' : $lang . '/') . $indexSlugConfig . '.html';
            }

            $sitemapPage = Page::fromArray([
                'title' => "Índice",
                'layout' => 'index_page',
                'slug' => $sitemapSlug,
                'date' => time(),
                'content' => '',
                'rawBody' => '',
                'lang' => $lang,
                'kind' => 'generic'
            ]);

            $this->createHTMLFile($sitemapPage);
            $this->createGeminiFile($sitemapPage);
            $this->createGopherFile($sitemapPage);
        }
    }

    /**
     * @param string $targetKind
     * @param array<int, Page> $pages
     */
    private function compileSectionIndexes(string $targetKind, array $pages): void
    {
        $defaultLang = $this->site->localization->defaultLang;
        $prettylinks = $this->site->options->prettylinks ?? true;

        // Group by language
        $grouped = [];
        $activeLangs = $this->site->localization->lang ?? [$defaultLang];
        foreach ($activeLangs as $l) {
            $grouped[$l] = [];
        }
        foreach ($pages as $p) {
            if (!in_array('draft', $p->metadata->tags)) {
                $grouped[$p->lang ?? $defaultLang][] = $p;
            }
        }

        foreach ($grouped as $lang => $kindPages) {
                usort($kindPages, function ($a, $b) {
                    $timeA = $a->date->getTimestamp();
                    $timeB = $b->date->getTimestamp();
                    return $timeB <=> $timeA;
                });

                $title = \Indieinabox\Helper::kindLabel($targetKind, $lang);
                $displayMode = \Indieinabox\Helper::getKindConfig($targetKind)['display_mode'] ?? 'default';

                $kindFolder = $this->getKindFolder($targetKind, $lang);
                $kindSlug = ($lang === $defaultLang ? '' : $lang . '/') . $kindFolder . '/';
                $indexRelpath = str_repeat('../', substr_count(ltrim($kindSlug, '/'), '/'));

                $content = '<ul style="list-style-type: none; padding-left: 0;">';
            foreach ($kindPages as $p) {
                $content .= '<li style="margin-bottom: 1.5em;">';
                if ($displayMode === 'thumbnail_snippet') {
                    // For photos/thumbnails
                    $content .= '<a href="' . $indexRelpath . ltrim($p->slug, '/') . '">' . $p->content . '</a>';
                    $content .= '<div style="font-size:0.9em; margin-top: 0.5em;">';
                    $content .= '<span style="opacity:0.8;">' . $p->localizeddate . '</span>';
                    $content .= '</div>';
                } else {
                    $content .= '<strong><a href="' . $indexRelpath . ltrim($p->slug, '/') . '">'
                        . htmlspecialchars($p->title) . '</a></strong>';
                    $content .= ' <span style="font-size:0.9em; opacity:0.8;">(' . $p->localizeddate . ')</span>';
                }
                $content .= '</li>';
            }
                $content .= '</ul>';

                $kindFolder = $this->getKindFolder($targetKind, $lang);
                $kindSlug = ($lang === $defaultLang ? '' : $lang . '/') . $kindFolder . '/';
            if (!$prettylinks) {
                $kindSlug = ($lang === $defaultLang ? '' : $lang . '/') . $kindFolder . '.html';
            }

                $indexPage = Page::fromArray([
                    'title'   => $title,
                    'layout'  => 'page',
                    'slug'    => $kindSlug,
                    'rawBody' => '',
                    'content' => $content,
                    'lang'    => $lang,
                    'kind'    => $targetKind
                ]);

                $this->createHTMLFile($indexPage);
                $this->createGeminiFile($indexPage);
                $this->createGopherFile($indexPage);
        }
    }
    
    /**
     * Ensures a mandatory homepage (index.html) exists in the output.
     * If one was not provided in the content directory, it creates a generic fallback.
     *
     * @return void
     */
    private function ensureMandatoryHomepage(): void
    {
        $langs = $this->site->localization->lang ?? ['en'];
        $defaultLang = $this->site->localization->defaultLang ?? 'en';
        
        foreach ($langs as $lang) {
            $expectedSlug = ($lang === $defaultLang) ? '/' : $lang . '/';
            $found = false;
            foreach ($this->pages as $p) {
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
                $this->pages->add($page);
            }
        }
    }
}
