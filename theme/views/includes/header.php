<?php
/** @var \Indieinabox\Page $page */
global $langLinks;
$links = $langLinks ?? [
    'pt' => '/',
    'en' => '/en/',
    'es' => '/es/'
];
?>
<header>
    <div class="lang-selector">
        [ <a href="<?= $links['pt'] ?>">PT</a> | <a href="<?= $links['en'] ?>">EN</a> | <a href="<?= $links['es'] ?>">ES</a> ]
    </div>
    <nav class="top-nav">
        [ <a href="<?= $page->relpath ?>"><?= \Indieinabox\Helper::translate('Início') ?></a> • <a href="<?= $page->relpath ?>indice/"><?= \Indieinabox\Helper::translate('Índice') ?></a> • <a href="<?= $page->relpath ?>agora/"><?= \Indieinabox\Helper::translate('Agora') ?></a> ]
    </nav>
    <div class="header-divider">--------------------------------------------------</div>
</header>
