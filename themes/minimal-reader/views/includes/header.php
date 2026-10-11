<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
use Indieinabox\Theme\ThemeData;
$activeLangLinks = $langLinks ?? ($GLOBALS['langLinks'] ?? null);
$activeHeaderLinks = $headerLinks ?? ($GLOBALS['headerLinks'] ?? []);
?>
<header>
    <div class="header-site-title">
        <a href="<?= $page->relpath ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($site->metadata->sitename ?? 'Blog') ?></a>
    </div>
    
    <?= ThemeData::getLanguageSelector($page, $site, $activeLangLinks) ?>
    <?= ThemeData::getHeaderNavLinks($page, $site, $activeHeaderLinks) ?>
</header>
