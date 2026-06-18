<?php
/** @var \Indieinabox\Page $page */
global $langLinks, $site, $urltranslations;
$links = $langLinks ?? [
    'pt' => '/',
    'en' => '/en/',
    'es' => '/es/'
];

$prettylinks = $site->options->prettylinks ?? true;
$lang = $page->lang ?? 'en';

$agoraSlug = 'agora';
if (isset($urltranslations['agora'][$lang])) {
    $agoraSlug = $urltranslations['agora'][$lang];
}

$indiceSlug = 'indice';

if ($prettylinks) {
    $homeLink = $page->relpath;
    $indiceLink = $page->relpath . $indiceSlug . '/';
    $agoraLink = $page->relpath . $agoraSlug . '/';
} else {
    $homeLink = $page->relpath . 'index.html';
    $indiceLink = $page->relpath . $indiceSlug . '.html';
    $agoraLink = $page->relpath . $agoraSlug . '.html';
}
?>
<header>
    <pre class="logo-figlet">
       _                           
      | |                          
    __| |_   _ _ __ ___   ___ _ __ 
   / _` | | | | '_ ` _ \ / _ \ '__|
~ | (_| | |_| | | | | | |  __/ |   
   \__,_|\__,_|_| |_| |_|\___|_|   
    </pre>
    <div class="lang-selector" style="text-align: center;">
        [ <a href="<?= $links['pt'] ?>">PT</a> | <a href="<?= $links['en'] ?>">EN</a> | <a href="<?= $links['es'] ?>">ES</a> ]
    </div>
    <nav class="top-nav" style="text-align: center;">
        [ <a href="<?= $homeLink ?>"><?= \Indieinabox\Helper::translate('Início') ?></a> • <a href="<?= $indiceLink ?>"><?= \Indieinabox\Helper::translate('Índice') ?></a> • <a href="<?= $agoraLink ?>"><?= \Indieinabox\Helper::translate('Agora') ?></a> ]
    </nav>
    <hr>
</header>
