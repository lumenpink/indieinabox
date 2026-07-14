<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
?>
<!DOCTYPE html>
<html lang="<?= $page->lang ?>">
<head>
    <?php include('includes/head.php'); ?>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
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
            include('includes/interactions.php');
            ?>
        </article>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
