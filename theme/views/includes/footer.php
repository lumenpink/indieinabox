<?php
/** @var \Indieinabox\Page $page */
global $site;
$lang = $page->lang ?? 'en';
$articlesFolder = ($lang === 'en' ? 'articles' : ($lang === 'es' ? 'articulos' : 'artigos'));
$notesFolder = ($lang === 'en' ? 'notes' : 'notas');
$photosFolder = ($lang === 'en' ? 'photos' : 'fotos');
$gardenFolder = ($lang === 'en' ? 'garden' : 'jardim');
$sobreSlug = ($lang === 'en' ? 'about-the-blog' : ($lang === 'es' ? 'sobre-el-blog' : 'sobre-o-blog'));

$prettylinks = $site->options->prettylinks ?? true;

if ($prettylinks) {
    $articlesLink = $page->relpath . $articlesFolder . '/';
    $notesLink = $page->relpath . $notesFolder . '/';
    $photosLink = $page->relpath . $photosFolder . '/';
    $gardenLink = $page->relpath . $gardenFolder . '/';
    $sobreLink = $page->relpath . $sobreSlug . '/';
} else {
    $articlesLink = $page->relpath . $articlesFolder . '.html';
    $notesLink = $page->relpath . $notesFolder . '/index.html';
    $photosLink = $page->relpath . $photosFolder . '.html';
    $gardenLink = $page->relpath . $gardenFolder . '.html';
    $sobreLink = $page->relpath . $sobreSlug . '.html';
}
?>
<footer>
    <div class="footer-divider">--------------------------------------------------</div>
    <div class="footer-links">
        <a href="<?= $articlesLink ?>"><?= \Indieinabox\Helper::translate('Artigos') ?></a> |
        <a href="<?= $notesLink ?>"><?= \Indieinabox\Helper::translate('Notas') ?></a> |
        <a href="<?= $photosLink ?>"><?= \Indieinabox\Helper::translate('Fotos') ?></a> |
        <a href="<?= $gardenLink ?>"><?= \Indieinabox\Helper::translate('Jardim') ?></a> |
        <a href="<?= $sobreLink ?>"><?= \Indieinabox\Helper::translate('Sobre') ?></a> |
        <a href="<?= $page->relpath ?>feed.xml">RSS</a>
    </div>
</footer>
