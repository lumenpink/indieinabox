<?php
$lang = $page->lang ?? 'en';
$articlesFolder = ($lang === 'en' ? 'articles' : ($lang === 'es' ? 'articulos' : 'artigos'));
$notesFolder = ($lang === 'en' ? 'notes' : 'notas');
$photosFolder = ($lang === 'en' ? 'photos' : 'fotos');
$gardenFolder = ($lang === 'en' ? 'garden' : 'jardim');
$sobreSlug = ($lang === 'en' ? 'about-the-blog' : ($lang === 'es' ? 'sobre-el-blog' : 'sobre-o-blog'));
?>
<footer>
    <div class="footer-divider">--------------------------------------------------</div>
    <div class="footer-links">
        <a href="<?= $page->relpath ?><?= $articlesFolder ?>/"><?= \Indieinabox\Helper::translate('Artigos') ?></a> |
        <a href="<?= $page->relpath ?><?= $notesFolder ?>/"><?= \Indieinabox\Helper::translate('Notas') ?></a> |
        <a href="<?= $page->relpath ?><?= $photosFolder ?>/"><?= \Indieinabox\Helper::translate('Fotos') ?></a> |
        <a href="<?= $page->relpath ?><?= $gardenFolder ?>/"><?= \Indieinabox\Helper::translate('Jardim') ?></a> |
        <a href="<?= $page->relpath ?><?= $sobreSlug ?>/"><?= \Indieinabox\Helper::translate('Sobre') ?></a> |
        <a href="<?= $page->relpath ?>feed.xml">RSS</a>
    </div>
</footer>
