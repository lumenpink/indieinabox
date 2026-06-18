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
        $notes = [];

        foreach ($this->pages as $page) {
            if ($page->kind === 'note') {
                $notes[] = $page;
            }
            $this->createHTMLFile($page);
            $this->createGeminiFile($page);
            $this->createGopherFile($page);
        }

        $this->compileConsolidatedNotes($notes);
        $this->compileSectionIndexes();
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

        $slug = $page->slug;
        $parts = explode('/', trim($slug, '/'));
        if (isset($parts[0]) && in_array($parts[0], ['en', 'es'])) {
            array_shift($parts);
        }
        if (isset($parts[0]) && in_array($parts[0], ['artigos', 'articles', 'articulos', 'notas', 'notes', 'fotos', 'photos', 'garden', 'jardim', 'pensamentos', 'thoughts', 'pensamientos'])) {
            array_shift($parts);
        }
        $nick = end($parts);
        if ($nick === false) {
            $nick = '';
        }

        $translationGroup = null;
        $baseKey = null;
        foreach ($urltranslations as $key => $langs) {
            if ($nick === $key) {
                $translationGroup = $langs;
                $baseKey = $key;
                break;
            }
            foreach ($langs as $lang => $translatedNick) {
                if ($nick === $translatedNick) {
                    $translationGroup = $langs;
                    $baseKey = $key;
                    break 2;
                }
            }
        }

        $links = [
            'pt' => '/',
            'en' => '/en/',
            'es' => '/es/',
        ];

        if ($translationGroup !== null && $baseKey !== null) {
            $kind = $page->kind;

            // PT URL
            $folderPt = $this->getKindFolder($kind, 'pt');
            $links['pt'] = '/' . ($folderPt ? $folderPt . '/' : '') . $baseKey . '/';
            if ($baseKey === 'index') {
                $links['pt'] = '/';
            }

            // EN URL
            if (isset($translationGroup['en'])) {
                $folderEn = $this->getKindFolder($kind, 'en');
                $links['en'] = '/en/' . ($folderEn ? $folderEn . '/' : '') . $translationGroup['en'] . '/';
                if ($translationGroup['en'] === 'index') {
                    $links['en'] = '/en/';
                }
            }

            // ES URL
            if (isset($translationGroup['es'])) {
                $folderEs = $this->getKindFolder($kind, 'es');
                $links['es'] = '/es/' . ($folderEs ? $folderEs . '/' : '') . $translationGroup['es'] . '/';
                if ($translationGroup['es'] === 'index') {
                    $links['es'] = '/es/';
                }
            }
        } else {
            if ($nick === 'index' || $nick === 'indice') {
                $links['pt'] = '/';
                $links['en'] = '/en/';
                $links['es'] = '/es/';
            } elseif ($nick === 'indice') {
                $links['pt'] = '/indice/';
                $links['en'] = '/en/index/';
                $links['es'] = '/es/indice/';
            }
        }

        foreach ($links as $lang => $url) {
            $links[$lang] = '/' . ltrim(preg_replace('#/+#', '/', $url), '/');
        }

        return $links;
    }

    private function getKindFolder(string $kind, string $lang): string
    {
        switch ($kind) {
            case 'article':
                if ($lang === 'en') return 'articles';
                if ($lang === 'es') return 'articulos';
                return 'artigos';
            case 'note':
                if ($lang === 'en') return 'notes';
                if ($lang === 'es') return 'notas';
                return 'notas';
            case 'photo':
                if ($lang === 'en') return 'photos';
                if ($lang === 'es') return 'fotos';
                return 'fotos';
            case 'jardim':
                if ($lang === 'en') return 'garden';
                if ($lang === 'es') return 'jardim';
                return 'jardim';
            default:
                return '';
        }
    }

    /**
     * @param \Indieinabox\Page[] $notes
     */
    private function compileConsolidatedNotes(array $notes): void
    {
        $grouped = [];
        foreach ($notes as $note) {
            $lang = $note->lang ?? 'en';
            $date = $note->date;
            $yearMonth = $date->format('Y-m');

            $grouped[$lang][$yearMonth][] = $note;
        }

        foreach ($grouped as $lang => &$months) {
            krsort($months);
            foreach ($months as $yearMonth => &$monthNotes) {
                usort($monthNotes, function ($a, $b) {
                    return $b->date->getTimestamp() <=> $a->date->getTimestamp();
                });
            }
            unset($monthNotes);
        }
        unset($months);

        $base = $this->site->paths->baseDir;
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $summaryFile = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "summary.php";

        foreach ($grouped as $lang => $months) {
            /** @var \Indieinabox\Page[] $allNotesForLang */
            $allNotesForLang = [];

            foreach ($months as $yearMonth => $monthNotes) {
                $monthSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/') . $this->getKindFolder('note', $lang) . '/' . $yearMonth . '/';
                $monthPage = Page::fromArray([
                    'title' => "Notas - " . $yearMonth,
                    'layout' => 'timeline',
                    'slug' => $monthSlug,
                    'date' => new \DateTime($yearMonth . '-01'),
                    'content' => '',
                    'rawBody' => '',
                    'lang' => $lang,
                    'kind' => 'note'
                ]);

                $monthContent = '';
                $monthRaw = '';
                foreach ($monthNotes as $idx => $note) {
                    if ($idx > 0) {
                        $monthContent .= "\n<hr class=\"divisor-bloco\">\n";
                        $monthRaw .= "\n\n---\n\n";
                    }
                    
                    if (file_exists($summaryFile)) {
                        ob_start();
                        global $site;
                        $site = $this->site;
                        $page = clone $note;
                        $page->relpath = $monthPage->relpath;
                        include $summaryFile;
                        $monthContent .= ob_get_clean();
                    } else {
                        $monthContent .= $note->content;
                    }
                    $monthRaw .= $note->rawBody;
                }

                $monthPage->content->content = $monthContent;
                $monthPage->content->rawBody = $monthRaw;

                $allNotesForLang = array_merge($allNotesForLang, $monthNotes);

                $this->createHTMLFile($monthPage);
                $this->createGeminiFile($monthPage);
                $this->createGopherFile($monthPage);
            }

            $indexSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/') . $this->getKindFolder('note', $lang) . '/';
            $indexPage = Page::fromArray([
                'title' => "Notas",
                'layout' => 'timeline',
                'slug' => $indexSlug,
                'date' => time(),
                'content' => '',
                'rawBody' => '',
                'lang' => $lang,
                'kind' => 'note'
            ]);

            $indexContent = '';
            $indexRaw = '';
            foreach ($allNotesForLang as $idx => $note) {
                if ($idx > 0) {
                    $indexContent .= "\n<hr class=\"divisor-bloco\">\n";
                    $indexRaw .= "\n\n---\n\n";
                }

                if (file_exists($summaryFile)) {
                    ob_start();
                    global $site;
                    $site = $this->site;
                    $page = clone $note;
                    $page->relpath = $indexPage->relpath;
                    include $summaryFile;
                    $indexContent .= ob_get_clean();
                } else {
                    $indexContent .= $note->content;
                }
                $indexRaw .= $note->rawBody;
            }

            $indexPage->content->content = $indexContent;
            $indexPage->content->rawBody = $indexRaw;

            $this->createHTMLFile($indexPage);
            $this->createGeminiFile($indexPage);
            $this->createGopherFile($indexPage);
        }
    }

    private function compileSectionIndexes(): void
    {
        $defaultLang = $this->site->localization->defaultLang;
        $prettylinks = $this->site->options->prettylinks ?? true;
        
        $langs = $this->site->localization->lang;
        if (!is_array($langs)) {
            $langs = [$langs];
        }

        foreach ($langs as $lang) {
            // 1. Sitemap (layout: indice)
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

            // 2. Kind Indexes (article, photo, jardim)
            $kinds = ['article', 'photo', 'jardim'];
            foreach ($kinds as $kind) {
                $kindPages = [];
                foreach ($this->pages as $p) {
                    if ($p->kind === $kind && $p->lang === $lang && !in_array('draft', $p->metadata->tags)) {
                        $kindPages[] = $p;
                    }
                }

                usort($kindPages, function($a, $b) {
                    $timeA = $a->date instanceof \DateTime ? $a->date->getTimestamp() : $a->date;
                    $timeB = $b->date instanceof \DateTime ? $b->date->getTimestamp() : $b->date;
                    return $timeB <=> $timeA;
                });

                $translatedTitle = $kind === 'jardim' ? 'Jardim' : ($kind === 'article' ? 'Artigos' : 'Fotos');
                $title = ucfirst(\Indieinabox\Helper::translate($translatedTitle));
                
                $content = '<ul style="list-style-type: none; padding-left: 0;">';
                foreach ($kindPages as $p) {
                    $content .= '<li style="margin-bottom: 1.5em;">';
                    if ($kind === 'photo') {
                        // For photos: show the rendered image then title/date below it
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

                $kindFolder = $this->getKindFolder($kind, $lang);
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
                    'kind'    => $kind
                ]);

                $this->createHTMLFile($indexPage);
                $this->createGeminiFile($indexPage);
                $this->createGopherFile($indexPage);
            }
        }
    }
}
