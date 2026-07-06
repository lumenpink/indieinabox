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
     * Method __construct
     * @param \Indieinabox\Site $site
     * @param ?\Indieinabox\Pages $pages
     * @param ?\Indieinabox\ParserInterface $parser
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
     * Method getPages
     * @return \Indieinabox\Pages
     */
    public function getPages(): Pages
    {
        return $this->pages;
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

        // Clean output directory
        Helper::recursiveRmdir($base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml);
        Helper::recursiveRmdir($base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGemini);
        Helper::recursiveRmdir($base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGopher);
        Helper::recursiveRmdir($base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirMedia);

        // Scan content
        $this->scan($this->site->paths->getContentPath());

        $this->ensureMandatoryHomepage();

        // Virtualize missing translations
        $this->virtualizeMissingLanguages();

        // Generate files
        $this->generateHTMLFiles();
        $this->generateTwtxt();
        $this->generateFeed();

        // Copy assets
        $this->copyAssets($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views");

        // Copy Media
        $this->copyMedia();

        // Copy static files
        if ($this->site->options->skipStatic) {
            echo "Skipping static files\n";
        } else {
            $this->copyStatic($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "static");
        }
    }
    /**
     * Method copyMedia
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
     * Method virtualizeMissingLanguages
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
     * Method pseudoTranslate
     * @param \Indieinabox\Page $page
     * @param string $targetLang
     * 
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
     * Method scan
     * @param string $dir
     * 
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
     * Method generateHTMLFiles
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
            $displayMode = $config['display_mode'] ?? 'default';

            if ($displayMode === 'full_content') {
                $this->compileTimelineIndexes($kind, $pagesForKind);
            } else {
                $this->compileSectionIndexes($kind, $pagesForKind);
            }
        }
    }

    /**
     * Method createHTMLFile
     * @param \Indieinabox\Page $page
     * 
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
            $page->shortlink = $shortlinkManager->getShortlink($page, $fqdn, $site->config['shortlink']);
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
            echo "Built " . $page->slug . "\n";
        } else {
            if (!is_dir($outDir . DIRECTORY_SEPARATOR . $destination)) {
                mkdir($outDir . DIRECTORY_SEPARATOR . $destination, 0777, true);
            }
            $destinationFile = $outDir
                . DIRECTORY_SEPARATOR
                . $destination
                . DIRECTORY_SEPARATOR
                . "index.html";
            echo "Built " . $page->slug . "index.html" . "\n";
        }
        $themeDir = $this->site->paths->themeDir ?? 'theme';
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

        file_put_contents($destinationFile, $fileContent);

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
        }
    }

    /**
     * Method generateFeed
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
     * Method copyAssets
     * @param string $dir
     * 
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
     * Method copyStatic
     * @param string $dir
     * 
     * @return bool
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
     * Method copyLiveJsFile
     * @param string $base
     * 
     * @return void
     */
    private function copyLiveJsFile(string $base): void
    {
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $jsDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml . DIRECTORY_SEPARATOR . "js";

        if (!is_dir($jsDir)) {
            mkdir($jsDir, 0777, true);
        }

        $liveJsFile = $base . "/" . $themeDir . "/views/livejs/live.js";
        echo "Copying static files: from $liveJsFile to $jsDir/live.js\n";
        if (file_exists($liveJsFile)) {
            $success = copy($liveJsFile, $jsDir . "/live.js");
            if (!$success) {
                echo "Failed to copy $liveJsFile\n";
            }
        } else {
            echo "File does not exist: $liveJsFile\n";
        }
    }

    /**
     * Method createGeminiFile
     * @param \Indieinabox\Page $page
     * 
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
            $destinationFile = $outDirGemini . DIRECTORY_SEPARATOR . dirname($destination)
                . DIRECTORY_SEPARATOR . basename($destination, $ext) . '.gmi';
        } else {
            if (!is_dir($outDirGemini . DIRECTORY_SEPARATOR . $destination)) {
                mkdir($outDirGemini . DIRECTORY_SEPARATOR . $destination, 0777, true);
            }
            $destinationFile = $outDirGemini
                . DIRECTORY_SEPARATOR
                . $destination
                . DIRECTORY_SEPARATOR
                . "index.gmi";
        }

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

    /**
     * Method createGopherFile
     * @param \Indieinabox\Page $page
     * 
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
            if (!is_dir($outDirGopher . DIRECTORY_SEPARATOR . $destination)) {
                mkdir($outDirGopher . DIRECTORY_SEPARATOR . $destination, 0777, true);
            }
            $destinationFile = $outDirGopher
                . DIRECTORY_SEPARATOR
                . $destination
                . DIRECTORY_SEPARATOR
                . "gophermap";
        }

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

    /**
     * Method generateTwtxt
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
            copy($feedFile, $langDirGemini . DIRECTORY_SEPARATOR . 'twtxt.txt');
            copy($feedFile, $langDirGopher . DIRECTORY_SEPARATOR . 'twtxt.txt');

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
        }

        // 2. Fetch aggregated timeline & mentions if subscriptions/hubs are configured
        echo "Fetching twtxt timeline and mentions...\n";
        $cacheDir = $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'twtxt_cache';

        $timelineEntries = [];
        $mentionEntries = [];

        if (!empty($this->site->twtxt->following)) {
            $timelineEntries = $twtxtManager->fetchTimeline($this->site->twtxt->following, $cacheDir);
        }
        if (!empty($this->site->twtxt->hubs)) {
            $mentionEntries = $twtxtManager->fetchHubMentions($this->site->twtxt->hubs, $this->site->metadata->fqdn);
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
     * Method getKindFolder
     * @param string $kind
     * @param string $lang
     * 
     * @return string
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
     * Method compileSitemap
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
     * Method ensureMandatoryHomepage
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
