<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
global $footerLinks;
use Indieinabox\Theme\ThemeData;

$baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
$sitename = $site->metadata->sitename ?? 'IndieInABox';
$author = $site->metadata->author ?? '';
?>
<footer role="contentinfo">
    <hr>
    <?= ThemeData::getFooterLinks($page, $footerLinks ?? []) ?>
    
    <div class="footer-credit">
        &copy; <?= date('Y') ?> <a href="<?= htmlspecialchars($baseUrl) ?>/"><?= htmlspecialchars($sitename) ?></a>
        <?php if (!empty($author)): ?>
            &bull; <span class="p-author"><?= htmlspecialchars($author) ?></span>
        <?php endif; ?>
        &bull; <span>Publicado com <a href="https://codeberg.org/indieinabox/indieinabox" target="_blank" rel="noopener">IndieInABox</a></span>
    </div>

    <div class="h-card p-author" style="display: none;">
        <a class="p-name u-url" rel="me" href="<?= htmlspecialchars($baseUrl) ?>"><?= htmlspecialchars($author ?: $sitename) ?></a>
        <img class="u-photo" src="<?= htmlspecialchars($baseUrl) ?>/media/default.png" alt="Author photo">
    </div>
</footer>

<?= ThemeData::getJsonLd($page, $site) ?>

