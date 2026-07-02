<?php
/** @var \Indieinabox\Page $page */
global $langLinks, $site, $urltranslations;

$langs = $site->localization->lang;
if (!is_array($langs)) {
    $langs = [$langs];
}
$defaultLang = $site->localization->defaultLang ?? 'en';
$lang = $page->lang ?? 'en';
$langPrefix = ($lang === $defaultLang) ? '' : $lang . '/';

if (!isset($langLinks)) {
    $links = [];
    foreach ($langs as $l) {
        if ($l === $defaultLang) {
            $links[$l] = $page->relpath;
        } else {
            $links[$l] = $page->relpath . $l . '/';
        }
    }
} else {
    $links = $langLinks;
}

$prettylinks = $site->options->prettylinks ?? true;

$indexSlugConfig = $site->config['index_slug'] ?? 'index';

if ($prettylinks) {
    $homeLink = $page->relpath . $langPrefix;
    $indexLink = $page->relpath . $langPrefix . $indexSlugConfig . '/';
} else {
    $homeLink = $page->relpath . ($langPrefix ? $langPrefix . 'index.html' : 'index.html');
    $indexLink = $page->relpath . $langPrefix . $indexSlugConfig . '.html';
}
?>
<header>
    <pre class="logo-figlet">       _                            
      | |_   _ _ __ ___   ___ _ __  
 /\/| | | | | | '_ ` _ \ / _ \ '_ \ 
|/\/  | | |_| | | | | | |  __/ | | |
      |_|\__,_|_| |_| |_|\___|_| |_|</pre>
    <?php if (count($langs) > 1): ?>
        <div class="lang-selector" style="text-align: center;">
            <?php 
            $langLinksHTML = [];
            foreach ($langs as $l) {
                $label = strtoupper($l);
                if ($l === $lang) {
                    $langLinksHTML[] = '<strong>' . htmlspecialchars($label) . '</strong>';
                } else {
                    $url = $links[$l] ?? '';
                    if ($url !== '') {
                        $langLinksHTML[] = '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
                    }
                }
            }
            echo '[ ' . implode(' &bull; ', $langLinksHTML) . ' ]';
            ?>
        </div>
    <?php endif; ?>
    <nav class="top-nav" style="text-align: center;">
        <?php
        global $headerLinks;
        $navItems = [];
        $navItems[] = '<a href="' . htmlspecialchars($homeLink) . '">' . \Indieinabox\Helper::translate('Home') . '</a>';
        $navItems[] = '<a href="' . htmlspecialchars($indexLink) . '">' . \Indieinabox\Helper::translate('Index') . '</a>';
        
        if (!empty($headerLinks)) {
            foreach ($headerLinks as $item) {
                $navItems[] = '<a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['label']) . '</a>';
            }
        }
        
        echo '[ ' . implode(' &bull; ', $navItems) . ' ]';
        ?>
    </nav>
    <hr>
</header>
