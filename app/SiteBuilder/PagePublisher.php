<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use Indieinabox\ActivityPubHandler;
use Indieinabox\Helper;
use Indieinabox\Markdown\ASTParser;
use Indieinabox\Markdown\GemtextRenderer;
use Indieinabox\Markdown\GophermapRenderer;
use Indieinabox\Page;
use Indieinabox\Pages;
use Indieinabox\ShortlinkManager;
use Indieinabox\Site;
use Indieinabox\SiteBuilder;
use Indieinabox\ThemeManager;

/**
 * Handles rendering, compilation, and file publication of Page objects
 * across HTML, Gemini, and Gopher protocols.
 */
class PagePublisher
{
    private Site $site;
    private Pages $pages;

    public function __construct(Site $site, Pages $pages)
    {
        $this->site = $site;
        $this->pages = $pages;
    }

    /**
     * Publishes a page across all supported output formats (HTML, Gemini, Gopher).
     *
     * @param Page $page
     * @return void
     */
    public function publish(Page $page): void
    {
        $this->publishHtml($page);
        $this->publishGemini($page);
        $this->publishGopher($page);
    }

    /**
     * Publishes multiple pages across all supported formats.
     *
     * @param iterable<Page> $pages
     * @return void
     */
    public function publishAll(iterable $pages): void
    {
        foreach ($pages as $page) {
            $this->publish($page);
        }
    }

    /**
     * Renders a single Page object into an HTML file using the configured theme.
     * Handles slug resolution, metadata extraction, ActivityPub JSON, interactions, and shortlink generation.
     *
     * @param Page $page The page to render.
     * @return void
     */
    public function publishHtml(Page $page): void
    {
        $base = $this->site->paths->baseDir;
        $site = $this->site;

        // Generate shortlink if enabled
        if (!empty($site->config['shortlink']['enabled'])) {
            $shortlinkManager = new ShortlinkManager();
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

        if (in_array('draft', $page->metadata->tags, true)) {
            return;
        }

        $destination = str_replace('/', DIRECTORY_SEPARATOR, $page->slug);
        $destination = trim($destination, DIRECTORY_SEPARATOR);
        $destination = preg_replace(
            '/^' . preg_quote($this->site->paths->contentDir, '/') . '/',
            '',
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
            $destinationFile = $destDir . DIRECTORY_SEPARATOR . 'index.html';
        }
        $destinationFile = preg_replace('#([^:])(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', '$1' . DIRECTORY_SEPARATOR, $destinationFile);
        $destinationFile = preg_replace('#^(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', DIRECTORY_SEPARATOR, $destinationFile);
        $themeDir = $this->site->paths->themeDir ?? 'theme';

        // True incremental build: skip if destination is newer than source and theme (only in dev mode)
        $skipGeneration = false;
        if (isset($this->site->options->dev) && $this->site->options->dev && empty($this->site->options->forceRebuild)) {
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
        $likes = Helper::getInteractions($page, 'like');
        $reposts = Helper::getInteractions($page, 'repost');
        $replies = Helper::getInteractions($page, 'reply');

        if (!$skipGeneration) {
            if (str_ends_with($destinationFile, '.html')) {
                echo 'Built ' . str_replace($outDir . DIRECTORY_SEPARATOR, '', $destinationFile) . "\n";
            } else {
                echo 'Built ' . $page->slug . "index.html\n";
            }
            ob_start();
            // phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative
            ThemeManager::loadView(
                $base . DIRECTORY_SEPARATOR . $themeDir . '/views/' . $page->metadata->layout . '.php',
                get_defined_vars()
            );
            $fileContent = ob_get_clean();

            if (isset($this->site->options->htmlpostprocessing)) {
                if ($this->site->options->htmlpostprocessing == 'beautify' || $this->site->options->dev) {
                    $fileContent = Helper::beautifyhtml($fileContent);
                }
                if ($this->site->options->htmlpostprocessing == 'minify' && !$this->site->options->dev) {
                    $fileContent = Helper::minifyhtml($fileContent);
                }
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
            $apObject = ActivityPubHandler::buildObjectForPageArray($postUrl, $actorId, $fqdn, $page->content->content, $title, $metadataArray);

            $jsonDestination = dirname($destinationFile) . DIRECTORY_SEPARATOR . 'index.json';
            if (str_ends_with($destinationFile, '.html') && basename($destinationFile) !== 'index.html') {
                $jsonDestination = substr($destinationFile, 0, -5) . '.json';
            }
            file_put_contents($jsonDestination, json_encode($apObject, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

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
                    $base . DIRECTORY_SEPARATOR . $themeDir . '/views/interactions_page.php',
                    $viewVars
                );
                $interactionsContent = ob_get_clean();

                if (isset($this->site->options->htmlpostprocessing)) {
                    if ($this->site->options->htmlpostprocessing == 'beautify' || $this->site->options->dev) {
                        $interactionsContent = Helper::beautifyhtml($interactionsContent);
                    }
                    if ($this->site->options->htmlpostprocessing == 'minify' && !$this->site->options->dev) {
                        $interactionsContent = Helper::minifyhtml($interactionsContent);
                    }
                }
                file_put_contents($interactionsFile, $interactionsContent);
            }

            // Generate local shortlink redirect
            if (isset($page->shortlink) && str_starts_with($page->shortlink, rtrim($fqdn, '/') . '/s/')) {
                $localShortlinkHash = substr(strrchr($page->shortlink, '/'), 1);
                $shortlinkDir = $outDir . DIRECTORY_SEPARATOR . 's' . DIRECTORY_SEPARATOR . $localShortlinkHash;
                if (!is_dir($shortlinkDir)) {
                    mkdir($shortlinkDir, 0777, true);
                }

                $targetUrl = rtrim($fqdn, '/') . '/' . ltrim($destination, '/');
                if (str_ends_with($targetUrl, '/index.html')) {
                    $targetUrl = substr($targetUrl, 0, -10);
                }

                $redirectContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Redirecting...</title>
    <meta http-equiv="refresh" content="0; url=' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '">
    <link rel="canonical" href="' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '">
</head>
<body>
    <p>Redirecting to <a href="' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '</a></p>
    <script>window.location.replace("' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '");</script>
</body>
</html>';

                $shortlinkFile = $shortlinkDir . DIRECTORY_SEPARATOR . 'index.html';
                file_put_contents($shortlinkFile, $redirectContent);
                SiteBuilder::addManifest($shortlinkFile);
                SiteBuilder::addManifest($shortlinkDir);
                SiteBuilder::addManifest($outDir . DIRECTORY_SEPARATOR . 's');
            }
        }

        // Add to manifest
        SiteBuilder::addManifest($destinationFile);

        $jsonDestination = dirname($destinationFile) . DIRECTORY_SEPARATOR . 'index.json';
        if (str_ends_with($destinationFile, '.html') && basename($destinationFile) !== 'index.html') {
            $jsonDestination = substr($destinationFile, 0, -5) . '.json';
        }
        if (file_exists($jsonDestination)) {
            SiteBuilder::addManifest($jsonDestination);
        }

        $interactionsFile = dirname($destinationFile) . DIRECTORY_SEPARATOR . 'interactions' . DIRECTORY_SEPARATOR . 'index.html';
        if (file_exists($interactionsFile)) {
            SiteBuilder::addManifest($interactionsFile);
            SiteBuilder::addManifest(dirname($interactionsFile));
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
                $base . DIRECTORY_SEPARATOR . $themeDir . '/views/interaction_reply.php',
                $viewVars
            );
            $replyContent = ob_get_clean();

            if (isset($this->site->options->htmlpostprocessing)) {
                if ($this->site->options->htmlpostprocessing == 'beautify' || $this->site->options->dev) {
                    $replyContent = Helper::beautifyhtml($replyContent);
                }
                if ($this->site->options->htmlpostprocessing == 'minify' && !$this->site->options->dev) {
                    $replyContent = Helper::minifyhtml($replyContent);
                }
            }
            file_put_contents($replyFile, $replyContent);
            SiteBuilder::addManifest($replyFile);
        }
    }

    /**
     * Renders a page into Gemini Gemtext (.gmi) and writes it to the Gemini output directory.
     *
     * @param Page $page The page to render.
     * @return void
     */
    public function publishGemini(Page $page): void
    {
        if (in_array('draft', $page->metadata->tags, true)) {
            return;
        }

        $base = $this->site->paths->baseDir;
        $destination = str_replace('/', DIRECTORY_SEPARATOR, $page->slug);
        $destination = trim($destination, DIRECTORY_SEPARATOR);
        $destination = preg_replace(
            '/^' . preg_quote($this->site->paths->contentDir, '/') . '/',
            '',
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
            $destinationFile = $destDir . DIRECTORY_SEPARATOR . 'index.gmi';
        }
        $destinationFile = preg_replace('#([^:])(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', '$1' . DIRECTORY_SEPARATOR, $destinationFile);
        $destinationFile = preg_replace('#^(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', DIRECTORY_SEPARATOR, $destinationFile);

        // True incremental build: skip if destination is newer than source (only in dev mode)
        $skipGeneration = false;
        if (isset($this->site->options->dev) && $this->site->options->dev && empty($this->site->options->forceRebuild)) {
            $mdMtime = ($page->filepath && file_exists($page->filepath)) ? filemtime($page->filepath) : 0;
            if (file_exists($destinationFile) && filemtime($destinationFile) >= $mdMtime) {
                $skipGeneration = true;
            }
        }

        if (!$skipGeneration) {
            echo 'Built ' . str_replace($outDirGemini . DIRECTORY_SEPARATOR, '', $destinationFile) . "\n";
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
        SiteBuilder::addManifest($destinationFile);
    }

    /**
     * Renders a page into Gopher format (gophermap) and writes it to the gopher output directory.
     * Formats links and metadata according to RFC 1436.
     *
     * @param Page $page The page to render.
     * @return void
     */
    public function publishGopher(Page $page): void
    {
        if (in_array('draft', $page->metadata->tags, true)) {
            return;
        }

        $base = $this->site->paths->baseDir;
        $destination = str_replace('/', DIRECTORY_SEPARATOR, $page->slug);
        $destination = trim($destination, DIRECTORY_SEPARATOR);
        $destination = preg_replace(
            '/^' . preg_quote($this->site->paths->contentDir, '/') . '/',
            '',
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
            $destinationFile = $destDir . DIRECTORY_SEPARATOR . 'gophermap';
        }
        $destinationFile = preg_replace('#([^:])(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', '$1' . DIRECTORY_SEPARATOR, $destinationFile);
        $destinationFile = preg_replace('#^(' . preg_quote(DIRECTORY_SEPARATOR, '#') . '){2,}#', DIRECTORY_SEPARATOR, $destinationFile);

        // True incremental build: skip if destination is newer than source (only in dev mode)
        $skipGeneration = false;
        if (isset($this->site->options->dev) && $this->site->options->dev && empty($this->site->options->forceRebuild)) {
            $mdMtime = ($page->filepath && file_exists($page->filepath)) ? filemtime($page->filepath) : 0;
            if (file_exists($destinationFile) && filemtime($destinationFile) >= $mdMtime) {
                $skipGeneration = true;
            }
        }

        if (!$skipGeneration) {
            echo 'Built ' . str_replace($outDirGopher . DIRECTORY_SEPARATOR, '', $destinationFile) . "\n";

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
            $gopherContent .= $formatInfo('');

            $gopherContent .= $gophermapRenderer->render($ast);
            $gopherContent .= $formatInfo('');
            $gopherContent .= "1Back to Home\t/\t{$host}\t70\r\n";

            file_put_contents($destinationFile, $gopherContent);
        }
        SiteBuilder::addManifest($destinationFile);
    }

    /**
     * @return array<string, string>
     */
    public function getLanguageLinks(Page $page): array
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

        if (isset($parts[0]) && in_array($parts[0], ['tag', 'flowerbed'], true)) {
            $taxLinks = [];
            $taxName = $parts[0];
            $taxTerm = $parts[1] ?? null;
            foreach ($langs as $l) {
                if ($taxTerm !== null) {
                    $translatedTerm = Helper::slugize(Helper::translate($taxTerm, $l));
                    $taxSubpath = $taxName . '/' . $translatedTerm . '/';
                } else {
                    $taxSubpath = $taxName . '/';
                }
                $taxLinks[$l] = ($l === $defaultLang ? '/' : '/' . $l . '/') . $taxSubpath;
            }
            return $taxLinks;
        }

        // Get localized folder names of all kinds in all active languages
        $kindFolders = [];
        if (!empty($this->site->config['kinds'])) {
            foreach ($this->site->config['kinds'] as $k => $conf) {
                foreach ($langs as $l) {
                    $kindFolders[] = Helper::getKindFolder($k, $l);
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

        $kind = $page->kind;

        foreach ($langs as $l) {
            $folder = '';
            if ($kind !== 'generic' && $kind !== 'page' && $kind !== 'home') {
                $folder = Helper::getKindFolder($kind, $l);
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
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getMenuLinks(Page $page): array
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

                // Hide if explicitly configured or if it's a system kind (generic/page)
                if (isset($conf['show_in_menu']) && !$conf['show_in_menu']) {
                    continue;
                }

                if (in_array($k, ['generic', 'page'], true)) {
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

                $folder = Helper::getKindFolder($k, $lang);
                if ($prettylinks) {
                    $url = $page->relpath . $langPrefix . $folder . '/';
                } else {
                    $url = $page->relpath . $langPrefix . $folder . '.html';
                }
                $label = Helper::kindLabel($k, $lang);
                $footerLinks[] = ['url' => $url, 'label' => $label, 'order' => PHP_INT_MAX];
            }
        }

        // 2. MD files with kind: page
        foreach ($this->pages as $p) {
            $pLang = $p->lang ?? $defaultLang;

            $menuVal = $p->metadata->menu ?? 'header';
            if ($menuVal === 'hide') {
                continue;
            }

            if ($pLang === $lang && $p->kind === 'page') {
                if (trim($p->slug, '/') === 'intro' || $p->nick === 'intro') {
                    continue;
                }
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
        $sortFn = function ($a, $b) {
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
}
