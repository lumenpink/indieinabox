<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
global $footerLinks;
use Indieinabox\Theme\ThemeData;

$baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
?>
<footer role="contentinfo">
    <hr>
    <?= ThemeData::getFooterLinks($page, $footerLinks ?? []) ?>
    
    <div class="h-card p-author" style="display: none;">
        <a class="p-name u-url" rel="me" href="<?= htmlspecialchars($baseUrl) ?>"><?= htmlspecialchars($site->metadata->author ?? 'Author') ?></a>
        <img class="u-photo" src="<?= htmlspecialchars($baseUrl) ?>/media/default.png" alt="Author photo">
    </div>
</footer>

<?= ThemeData::getJsonLd($page, $site) ?>

<?php if (isset($site->options->dev) && $site->options->dev): ?>
<script src="<?= $site->paths->baseDir ?? '' ?>/js/live.js"></script>
<?php endif; ?>
