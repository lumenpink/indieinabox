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

$agoraSlug = 'agora';
if ($lang !== 'pt' && isset($urltranslations['agora'][$lang])) {
    $agoraSlug = $urltranslations['agora'][$lang];
}

$indiceSlug = 'indice';

if ($prettylinks) {
    $homeLink = $page->relpath . $langPrefix;
    $indiceLink = $page->relpath . $langPrefix . $indiceSlug . '/';
    $agoraLink = $page->relpath . $langPrefix . $agoraSlug . '/';
} else {
    $homeLink = $page->relpath . ($langPrefix ? $langPrefix . 'index.html' : 'index.html');
    $indiceLink = $page->relpath . $langPrefix . $indiceSlug . '.html';
    $agoraLink = $page->relpath . $langPrefix . $agoraSlug . '.html';
}
?>
<header>
    <pre class="logo-figlet">
       _
      | |
   ~  | | _   _ _ __ ___   ___ _ __
      | || | | | '_ ` _ \ / _ \ '_ \
      | || |_| | | | | | |  __/ | | |
      |_| \__,_|_| |_| |_|\___|_| |_|
    </pre>
    <?php if (count($langs) > 1): ?>
        <div class="lang-selector" style="text-align: center;">
            [
            <?php 
            $langLinksHTML = [];
            foreach ($langs as $l) {
                $label = strtoupper($l);
                $url = $links[$l] ?? '';
                if ($url !== '') {
                    $langLinksHTML[] = '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
                }
            }
            echo implode(' | ', $langLinksHTML);
            ?>
            ]
        </div>
    <?php endif; ?>
    <nav class="top-nav" style="text-align: center;">
        [ <a href="<?= $homeLink ?>"><?= \Indieinabox\Helper::translate('Home') ?></a> • <a href="<?= $indiceLink ?>"><?= \Indieinabox\Helper::translate('Index') ?></a> • <a href="<?= $agoraLink ?>"><?= \Indieinabox\Helper::translate('Now') ?></a> ]
    </nav>
    <hr>
</header>
