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
        &copy; <?= date('Y') ?> <?= htmlspecialchars($site->metadata->author ?? 'Autor') ?> · 
        Publicado com <a href="https://codeberg.org/indieinabox/indieinabox" target="_blank" rel="noopener">IndieInABox</a>
    </div>
</footer>
