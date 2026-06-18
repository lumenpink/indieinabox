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
    <div class="lang-selector">
        [ <a href="<?= $links['pt'] ?>">PT</a> | <a href="<?= $links['en'] ?>">EN</a> | <a href="<?= $links['es'] ?>">ES</a> ]
    </div>
    <nav class="top-nav">
        [ <a href="<?= $homeLink ?>"><?= \Indieinabox\Helper::translate('Início') ?></a> • <a href="<?= $indiceLink ?>"><?= \Indieinabox\Helper::translate('Índice') ?></a> • <a href="<?= $agoraLink ?>"><?= \Indieinabox\Helper::translate('Agora') ?></a> ]
    </nav>
    <div class="header-divider">--------------------------------------------------</div>
</header>
