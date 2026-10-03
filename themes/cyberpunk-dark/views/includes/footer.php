<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
global $footerLinks;
use Indieinabox\Theme\ThemeData;
?>
<footer role="contentinfo">
    <?= ThemeData::getFooterLinks($page, $footerLinks ?? []) ?>
    
    <div class="footer-credit">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($site->metadata->author ?? 'Autor') ?>.
        <br>
        [SYSTEM: <a href="https://codeberg.org/indieinabox/indieinabox" target="_blank" rel="noopener">IndieInABox</a> // CYBERPUNK_DARK]
    </div>
</footer>
