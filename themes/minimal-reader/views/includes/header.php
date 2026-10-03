<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
global $langLinks, $headerLinks;
use Indieinabox\Theme\ThemeData;
?>
<header>
    <div class="header-site-title">
        <a href="<?= $page->relpath ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($site->metadata->sitename ?? 'Blog') ?></a>
    </div>
    
    <?= ThemeData::getLanguageSelector($page, $site, $langLinks ?? null) ?>
    <?= ThemeData::getHeaderNavLinks($page, $site, $headerLinks ?? []) ?>
</header>
