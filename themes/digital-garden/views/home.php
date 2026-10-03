<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
/** @var \Indieinabox\Page\Pages $pages */
?>
<!DOCTYPE html>
<html lang="<?= $page->lang ?>">
<head>
    <?php \Indieinabox\Theme\ThemeManager::includeView('includes/head.php', get_defined_vars()); ?>
</head>
<body>
    <?php \Indieinabox\Theme\ThemeManager::includeView('includes/header.php', get_defined_vars()); ?>
    
    <main>
        <?= \Indieinabox\Theme\ThemeData::getHCard($page, $site) ?>

        <?php if (!empty($page->content) && (string)$page->content !== ''): ?>
            <div class="intro-block" style="margin-bottom: 2.5rem; background: var(--surface-card); padding: 1.25rem; border-radius: 8px;">
                <?= $page->content ?>
            </div>
        <?php endif; ?>

        <section class="recent-posts">
            <h2>🌿 Mudas Recentes & Anotações</h2>
            <?= \Indieinabox\Theme\ThemeData::getSummaryList($page, $site, $pages) ?>
        </section>
    </main>
    
    <?php \Indieinabox\Theme\ThemeManager::includeView('includes/footer.php', get_defined_vars()); ?>
</body>
</html>
