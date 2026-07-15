<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
?>
<!DOCTYPE html>
<html lang="<?= $page->lang ?>">
<head>
    <?php \Indieinabox\ThemeManager::includeView('includes/head.php', get_defined_vars()); ?>
</head>
<body>
    <?php \Indieinabox\ThemeManager::includeView('includes/header.php', get_defined_vars()); ?>
    
    <main>
        <article class="h-entry">
            <?php if (\Indieinabox\Helper::getKindConfig($page->kind)['has_title'] && ($page->metadata->hide_title ?? false) !== true): ?>
                <h1 class="p-name"><?= htmlspecialchars($page->title) ?></h1>
            <?php endif; ?>
            
            <?= \Indieinabox\Theme\ThemeHelper::getMetadataHtml($page) ?>
            <?= \Indieinabox\Theme\ThemeHelper::getIndieWebContext($page) ?>

            <div class="e-content" style="margin-left: 2em;">
                <?= \Indieinabox\Theme\ThemeHelper::getAITranslationNotice($page) ?>
                <?= $page->content ?>
            </div>

            <?= \Indieinabox\Theme\ThemeHelper::getSyndicationLinks($page) ?>

            <?php
            $p = $page;
            \Indieinabox\ThemeManager::includeView('includes/interactions.php', get_defined_vars());
            ?>
        </article>
    </main>
    
    <?php \Indieinabox\ThemeManager::includeView('includes/footer.php', get_defined_vars()); ?>
</body>
</html>
