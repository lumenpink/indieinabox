<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
global $footerLinks;
use Indieinabox\Theme\ThemeData;

$baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
$sitename = $site->metadata->sitename ?? 'IndieInABox';
$author = $site->metadata->author ?? '';
$lang = $page->lang ?? ($site->localization->defaultLang ?? 'pt');
?>
<footer role="contentinfo">
    <hr>
    <?= ThemeData::getFooterLinks($page, $footerLinks ?? []) ?>
    
    <?= ThemeData::getCanonicalFooter($lang) ?>

    <div class="h-card p-author" style="display: none;">
        <a class="p-name u-url" rel="me" href="<?= htmlspecialchars($baseUrl) ?>"><?= htmlspecialchars($author ?: $sitename) ?></a>
        <img class="u-photo" src="<?= htmlspecialchars($baseUrl) ?>/media/default.png" alt="Author photo">
    </div>
</footer>

<?= ThemeData::getJsonLd($page, $site) ?>

