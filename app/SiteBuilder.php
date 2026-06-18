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

class SiteBuilder
{
    private Site $site;
    private Pages $pages;
    private ParserInterface $parser;

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

    public function getPages(): Pages
    {
        return $this->pages;
    }

    public function build(): void
    {
        $base = $this->site->paths->baseDir;
        $themeDir = $this->site->paths->themeDir ?? 'theme';

        // Clean output directory
        Helper::recursive_rmdir($base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir);

        // Scan content
        $this->scan($base . DIRECTORY_SEPARATOR . $this->site->paths->contentDir);

        // Virtualize missing translations
        $this->virtualizeMissingLanguages();

        // Generate files
        $this->generateHTMLFiles();
        $this->generateTwtxt();
        $this->generateFeed();

        // Copy assets
        $this->copyAssets($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views");

        // Copy static files
        if ($this->site->options->skipStatic) {
            echo "Skipping static files\n";
        } else {
            $this->copyStatic($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "static");
        }
    }

    private function virtualizeMissingLanguages(): void
    {
        $langs = $this->site->localization->lang;
        if (!is_array($langs) || count($langs) <= 1) {
            return;
        }

        $defaultLang = $this->site->localization->defaultLang ?? 'en';
        $prettylinks = $this->site->options->prettylinks ?? true;

        // Collect existing pages to quickly look them up by kind + nick + language
        $existing = [];
        $defaultLangPages = [];
        foreach ($this->pages as $page) {
            $lang = $page->lang ?? $defaultLang;
            $nick = $page->nick ?? '';
            $kind = $page->kind ?? '';
            
            $existing["{$kind}:{$nick}:{$lang}"] = $page;
            
            if ($lang === $defaultLang) {
                $defaultLangPages[] = $page;
            }
        }

        // For each page in the default language, check if it has a localized version in other languages
        foreach ($defaultLangPages as $page) {
            // Only virtualize user content kinds, not generic index/home pages
            if (in_array($page->kind, ['generic', 'page', 'home'], true)) {
                continue;
            }

            foreach ($langs as $lang) {
                if ($lang === $defaultLang) {
                    continue;
                }

                $key = "{$page->kind}:{$page->nick}:{$lang}";
                if (!isset($existing[$key])) {
                    // No translation exists for this language! Let's virtualize it!
                    $cloned = clone $page;
                    
                    // Update language of the cloned page
                    $cloned->lang = $lang;
                    
                    // Adjust title or text
                    $prefix = '[' . strtoupper($lang) . '] ';
                    $hasTitle = !empty($cloned->title) && $cloned->title !== 'Untitled' && $cloned->title !== 'untitled';
                    
                    // Check if the kind config allows a title
                    $kindConfig = \Indieinabox\Helper::getKindConfig($cloned->kind);
                    if (isset($kindConfig['has_title']) && !$kindConfig['has_title']) {
                        $hasTitle = false;
                    }

                    if ($hasTitle) {
                        $cloned->title = $prefix . $cloned->title;
                    } else {
                        // Prepend language code directly to text
                        $cloned->content->content = $prefix . $cloned->content->content;
                        $cloned->content->rawBody = $prefix . $cloned->content->rawBody;
                    }

                    // Build its slug preserving subfolder structure
                    $kindFolder = $this->getKindFolder($cloned->kind, $lang);
                    
                    $defaultKindFolder = $this->getKindFolder($page->kind, $defaultLang);
                    $cleanSlug = trim($page->slug, '/');
                    // Remove defaultKindFolder prefix if present
                    if (str_starts_with($cleanSlug, $defaultKindFolder . '/')) {
                        $cleanSlug = substr($cleanSlug, strlen($defaultKindFolder . '/'));
                    } elseif ($cleanSlug === $defaultKindFolder) {
                        $cleanSlug = '';
                    }

                    if ($prettylinks) {
                        $cloned->slug = $lang . '/' . $kindFolder . '/' . ($cleanSlug !== '' ? $cleanSlug . '/' : '');
                    } else {
                        // For non-prettylinks, it ends in .html
                        if (str_ends_with($cleanSlug, '.html')) {
                            $cleanSlug = substr($cleanSlug, 0, -5);
                        }
                        $cloned->slug = $lang . '/' . $kindFolder . '/' . $cleanSlug . '.html';
                    }

                    // Recalculate relative path
                    $cleanSlugPath = ltrim($cloned->slug, '/');
                    if ($cleanSlugPath === '' || $cleanSlugPath === 'index.html') {
                        $cloned->relpath = './';
                    } else {
                        $slashCount = substr_count($cleanSlugPath, '/');
                        $cloned->relpath = $slashCount > 0 ? str_repeat('../', $slashCount) : './';
                    }

                    // Process other language pathways through LanguageProcessor
                    global $urltranslations;
                    $urlTranslationsObj = new UrlTranslations($urltranslations ?? []);
                    $languageProcessor = new LanguageProcessor($this->site, $urlTranslationsObj);
                    $cloned = $languageProcessor->processLanguage($cloned);

                    // Add to our pages collection
                    $this->pages->add($cloned);
                    
                    // Mark as existing so we don't duplicate
                    $existing[$key] = $cloned;
                }
            }
        }
    }

    public function scan(string $dir): void
    {
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
                        && strpos($path, DIRECTORY_SEPARATOR . $this->site->paths->outputDir) === false
                    ) {
                        $this->scan($path);
                    }
                }
            }
        }
    }

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

    private function createHTMLFile(Page $page): void
    {
        $base = $this->site->paths->baseDir;
        $site = $this->site;
        // Expose $p, $pages, $site and $langLinks to the global scope for view template compatibility
        global $p, $site, $pages, $langLinks;
        $p = $page;
        $pages = $this->pages;
        $langLinks = $this->getLanguageLinks($page);

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

        $outDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir;

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
        include $base . DIRECTORY_SEPARATOR . $themeDir . "/views/" . $page->metadata->layout . ".php"; // NOSONAR
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
    }

    public function generateFeed(): void
    {
        $base = $this->site->paths->baseDir;
        $site = $this->site;
        $pages = $this->pages;
        // Expose to global scope for view template compatibility
        global $pages, $site;

        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $file = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "feed" . ".php";
        if (file_exists($file) && is_readable($file)) {
            include $file;
        }
    }

    public function copyAssets(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $base = $this->site->paths->baseDir;
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry !== "." && $entry !== "..") {
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path)) {
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    if ($ext === "js" || $ext === "css") {
                        $filename = pathinfo($path, PATHINFO_FILENAME);
                        $assetsDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir . DIRECTORY_SEPARATOR . "assets";

                        if (!is_dir($assetsDir)) {
                            mkdir($assetsDir, 0777, true);
                        }

                        copy(
                            $path,
                            $assetsDir . DIRECTORY_SEPARATOR . $filename . "." . $ext
                        );
                    }
                } else {
                    $this->copyAssets($path);
                }
            }
        }
    }

    public function copyStatic(string $dir): bool
    {
        $base = $this->site->paths->baseDir;

        if (!is_dir($dir)) {
            return false;
        }

        echo "Copying static files\n";
        $this->copyStaticFiles($dir, $base);

        if ($this->site->options->dev) {
            $this->copyLiveJsFile($base);
        }

        return true;
    }

    private function copyStaticFiles(string $dir, string $base): void
    {
        $entries = Helper::getDirContents($dir);

        foreach ($entries as $entry) {
            if ($this->shouldSkipEntry($entry)) {
                continue;
            }

            $destination = $this->getDestinationPath($entry, $dir, $base);

            if ($this->shouldCopyFile($entry, $destination)) {
                $this->ensureDestinationDirectoryExists($destination);
                copy($entry, $destination);
            }
        }
    }

    private function shouldSkipEntry(string $entry): bool
    {
        return $entry === "." || $entry === "..";
    }

    private function getDestinationPath(string $entry, string $dir, string $base): string
    {
        $path = str_replace($dir . DIRECTORY_SEPARATOR, "", $entry);
        $filepath = pathinfo($path, PATHINFO_DIRNAME);
        $fullfilename = pathinfo($path, PATHINFO_BASENAME);

        return $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir . DIRECTORY_SEPARATOR . $filepath . DIRECTORY_SEPARATOR . $fullfilename;
    }

    private function shouldCopyFile(string $source, string $destination): bool
    {
        return is_file($source)
            && (!is_file($destination)
                || filemtime($source) > filemtime($destination)
                || $this->site->options->forceStaticOverride);
    }

    private function ensureDestinationDirectoryExists(string $destination): void
    {
        $directory = pathinfo($destination, PATHINFO_DIRNAME);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    private function copyLiveJsFile(string $base): void
    {
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $jsDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir . DIRECTORY_SEPARATOR . "js";

        if (!is_dir($jsDir)) {
            mkdir($jsDir, 0777, true);
        }

        $liveJsFile = $base . "/" . $themeDir . "/views/livejs/live.js";
        if (file_exists($liveJsFile)) {
            copy($liveJsFile, $jsDir . "/live.js");
        }
    }

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

        $outDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir;
        if (str_ends_with($destination, '.html') || str_ends_with($destination, '.htm')) {
            $ext = str_ends_with($destination, '.html') ? '.html' : '.htm';
            $dir = dirname($outDir . DIRECTORY_SEPARATOR . $destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $destinationFile = $outDir . DIRECTORY_SEPARATOR . dirname($destination) . DIRECTORY_SEPARATOR . basename($destination, $ext) . '.gmi';
        } else {
            if (!is_dir($outDir . DIRECTORY_SEPARATOR . $destination)) {
                mkdir($outDir . DIRECTORY_SEPARATOR . $destination, 0777, true);
            }
            $destinationFile = $outDir
                . DIRECTORY_SEPARATOR
                . $destination
                . DIRECTORY_SEPARATOR
                . "index.gmi";
        }

        echo "Built " . str_replace($outDir . DIRECTORY_SEPARATOR, '', $destinationFile) . "\n";

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

        $outDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir;
        if (str_ends_with($destination, '.html') || str_ends_with($destination, '.htm')) {
            $ext = str_ends_with($destination, '.html') ? '.html' : '.htm';
            $dir = dirname($outDir . DIRECTORY_SEPARATOR . $destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $destinationFile = $outDir . DIRECTORY_SEPARATOR . dirname($destination) . DIRECTORY_SEPARATOR . basename($destination, $ext) . '.gophermap';
        } else {
            if (!is_dir($outDir . DIRECTORY_SEPARATOR . $destination)) {
                mkdir($outDir . DIRECTORY_SEPARATOR . $destination, 0777, true);
            }
            $destinationFile = $outDir
                . DIRECTORY_SEPARATOR
                . $destination
                . DIRECTORY_SEPARATOR
                . "gophermap";
        }

        echo "Built " . str_replace($outDir . DIRECTORY_SEPARATOR, '', $destinationFile) . "\n";

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

        $formatInfo = function(string $text): string {
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

    public function generateTwtxt(): void
    {
        $base = $this->site->paths->baseDir;
        $outDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir;
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }

        // 1. Generate local feed: public/twtxt.txt
        $twtxtManager = new \Indieinabox\Twtxt\TwtxtManager();
        $feedFile = $outDir . DIRECTORY_SEPARATOR . 'twtxt.txt';

        echo "Generating twtxt.txt feed...\n";
        $twtxtManager->generateFeed(
            iterator_to_array($this->pages),
            $feedFile,
            $this->site->metadata->fqdn,
            $this->site->twtxt
        );

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
        $layoutFile = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'timeline.php';
        if (file_exists($layoutFile) && is_readable($layoutFile)) {
            $this->createHTMLFile($timelinePage);
        } else {
            echo "Skipping timeline static page compilation: timeline layout not found.\n";
        }
    }

    private function getLanguageLinks(Page $page): array
    {
        global $urltranslations;
        if (!is_array($urltranslations)) {
            $urltranslations = [];
        }

        $langs = $this->site->localization->lang;
        if (!is_array($langs)) {
            $langs = [$langs];
        }
        $defaultLang = $this->site->localization->defaultLang ?? 'en';
        $prettylinks = $this->site->options->prettylinks ?? true;

        $slug = $page->slug;
        $parts = explode('/', trim($slug, '/'));
        if (isset($parts[0]) && in_array($parts[0], $langs, true) && $parts[0] !== $defaultLang) {
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

        if ($baseKey !== null && $baseKey !== '') {
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
                
                if ($localizedSlugPart === 'index') {
                    if ($l === $defaultLang) {
                        $links[$l] = '/';
                    } else {
                        $links[$l] = '/' . $l . '/';
                    }
                } else {
                    if ($prettylinks) {
                        if ($l === $defaultLang) {
                            $links[$l] = '/' . ($folder ? $folder . '/' : '') . $localizedSlugPart . '/';
                        } else {
                            $links[$l] = '/' . $l . '/' . ($folder ? $folder . '/' : '') . $localizedSlugPart . '/';
                        }
                    } else {
                        if ($l === $defaultLang) {
                            $links[$l] = '/' . ($folder ? $folder . '/' : '') . $localizedSlugPart . '.html';
                        } else {
                            $links[$l] = '/' . $l . '/' . ($folder ? $folder . '/' : '') . $localizedSlugPart . '.html';
                        }
                    }
                }
            }
        }

        foreach ($links as $lang => $url) {
            $links[$lang] = '/' . ltrim(preg_replace('#/+#', '/', $url), '/');
        }

        return $links;
    }

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
            $lang = $p->lang ?? 'en';
            $date = $p->date;
            $yearMonth = $date instanceof \DateTime ? $date->format('Y-m') : date('Y-m', $date);

            $grouped[$lang][$yearMonth][] = $p;
        }

        foreach ($grouped as $lang => &$months) {
            krsort($months);
            foreach ($months as $yearMonth => &$monthPages) {
                usort($monthPages, function ($a, $b) {
                    $timeA = $a->date instanceof \DateTime ? $a->date->getTimestamp() : $a->date;
                    $timeB = $b->date instanceof \DateTime ? $b->date->getTimestamp() : $b->date;
                    return $timeB <=> $timeA;
                });
            }
            unset($monthPages);
        }
        unset($months);

        $base = $this->site->paths->baseDir;
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $summaryFile = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "summary.php";

        foreach ($grouped as $lang => $months) {
            /** @var \Indieinabox\Page[] $allPagesForLang */
            $allPagesForLang = [];
            $titleBase = \Indieinabox\Helper::kindLabel($targetKind, $lang);

            foreach ($months as $yearMonth => $monthPages) {
                $monthSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/') . $this->getKindFolder($targetKind, $lang) . '/' . $yearMonth . '/';
                $monthPage = Page::fromArray([
                    'title' => $titleBase . " - " . $yearMonth,
                    'layout' => 'timeline',
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
                        include $summaryFile;
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

            $indexSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/') . $this->getKindFolder($targetKind, $lang) . '/';
            $indexPage = Page::fromArray([
                'title' => $titleBase,
                'layout' => 'timeline',
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
                    include $summaryFile;
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

    private function compileSitemap(): void
    {
        $defaultLang = $this->site->localization->defaultLang;
        $prettylinks = $this->site->options->prettylinks ?? true;
        
        $langs = $this->site->localization->lang;
        if (!is_array($langs)) {
            $langs = [$langs];
        }

        foreach ($langs as $lang) {
            $sitemapSlug = ($lang === $defaultLang ? '' : $lang . '/') . 'indice/';
            if (!$prettylinks) {
                $sitemapSlug = ($lang === $defaultLang ? '' : $lang . '/') . 'indice.html';
            }

            $sitemapPage = Page::fromArray([
                'title' => "Índice",
                'layout' => 'indice',
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

    private function compileSectionIndexes(string $targetKind, array $pages): void
    {
        $defaultLang = $this->site->localization->defaultLang;
        $prettylinks = $this->site->options->prettylinks ?? true;
        
        // Group by language
        $grouped = [];
        foreach ($pages as $p) {
            if (!in_array('draft', $p->metadata->tags)) {
                $grouped[$p->lang ?? $defaultLang][] = $p;
            }
        }

        foreach ($grouped as $lang => $kindPages) {

                usort($kindPages, function($a, $b) {
                    $timeA = $a->date instanceof \DateTime ? $a->date->getTimestamp() : $a->date;
                    $timeB = $b->date instanceof \DateTime ? $b->date->getTimestamp() : $b->date;
                    return $timeB <=> $timeA;
                });

                $title = \Indieinabox\Helper::kindLabel($targetKind, $lang);
                
                $displayMode = \Indieinabox\Helper::getKindConfig($targetKind)['display_mode'] ?? 'default';

                $content = '<ul style="list-style-type: none; padding-left: 0;">';
                foreach ($kindPages as $p) {
                    $content .= '<li style="margin-bottom: 1.5em;">';
                    if ($displayMode === 'thumbnail_snippet') {
                        // For photos/thumbnails
                        $content .= '<a href="' . $p->relpath . $p->slug . '">' . $p->content . '</a>';
                        $content .= '<div style="font-size:0.9em; margin-top: 0.5em;">';
                        $content .= '<span style="opacity:0.8;">' . $p->localizeddate . '</span>';
                        $content .= '</div>';
                    } else {
                        $content .= '<strong><a href="' . $p->relpath . $p->slug . '">' . htmlspecialchars($p->title) . '</a></strong>';
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
}
