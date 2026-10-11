<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
use Indieinabox\Theme\ThemeData;
$activeFooterLinks = $footerLinks ?? ($GLOBALS['footerLinks'] ?? []);
?>
<footer role="contentinfo">
    <?= ThemeData::getKindLinks($page, $pages ?? null, $site) ?>
    <?= ThemeData::getFooterLinks($page, $activeFooterLinks) ?>
    
    <div class="footer-credit">
        🌿 Cultivado com carinho por <?= htmlspecialchars($site->metadata->author ?? 'Jardineiro') ?> &bull;
        Desenvolvido com <a href="https://codeberg.org/indieinabox/indieinabox" target="_blank" rel="noopener">IndieInABox</a>
    </div>
</footer>
