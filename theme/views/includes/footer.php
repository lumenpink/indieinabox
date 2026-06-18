<?php
/** @var \Indieinabox\Page $page */
global $site;
$lang = $page->lang ?? 'en';
$defaultLang = $site->localization->defaultLang ?? 'en';
$langPrefix = ($lang === $defaultLang) ? '' : $lang . '/';

$articlesFolder = \Indieinabox\Helper::getKindFolder('article', $lang);
$notesFolder = \Indieinabox\Helper::getKindFolder('note', $lang);
$photosFolder = \Indieinabox\Helper::getKindFolder('photo', $lang);
$gardenFolder = \Indieinabox\Helper::getKindFolder('garden', $lang);
$sobreSlug = ($lang === 'en' ? 'about-the-blog' : ($lang === 'es' ? 'sobre-el-blog' : 'sobre-o-blog'));

$prettylinks = $site->options->prettylinks ?? true;

if ($prettylinks) {
    $articlesLink = $page->relpath . $langPrefix . $articlesFolder . '/';
    $notesLink = $page->relpath . $langPrefix . $notesFolder . '/';
    $photosLink = $page->relpath . $langPrefix . $photosFolder . '/';
    $gardenLink = $page->relpath . $langPrefix . $gardenFolder . '/';
    $sobreLink = $page->relpath . $langPrefix . $sobreSlug . '/';
} else {
    $articlesLink = $page->relpath . $langPrefix . $articlesFolder . '.html';
    $notesLink = $page->relpath . $langPrefix . $notesFolder . '/index.html';
    $photosLink = $page->relpath . $langPrefix . $photosFolder . '.html';
    $gardenLink = $page->relpath . $langPrefix . $gardenFolder . '.html';
    $sobreLink = $page->relpath . $langPrefix . $sobreSlug . '.html';
}
?>
<footer>
    <hr>
    <div class="footer-links" style="text-align: center;">
        <a href="<?= $articlesLink ?>"><?= \Indieinabox\Helper::translate('Articles') ?></a> |
        <a href="<?= $notesLink ?>"><?= \Indieinabox\Helper::translate('Notes') ?></a> |
        <a href="<?= $photosLink ?>"><?= \Indieinabox\Helper::translate('Photos') ?></a> |
        <a href="<?= $gardenLink ?>"><?= \Indieinabox\Helper::translate('Garden') ?></a> |
        <a href="<?= $sobreLink ?>"><?= \Indieinabox\Helper::translate('About') ?></a> |
        <a href="<?= $page->relpath ?>feed.xml">RSS</a>
    </div>
</footer>
