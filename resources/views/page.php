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
            
            <?php
            // Only show date metadata when the page has a real author-assigned date
            // and it is not a structural kind (page, home, generic).
            $showMetadata = !in_array($page->kind, ['page', 'home', 'generic'], true)
                && !empty($page->isodate)
                && $page->localizeddate !== 'Saturday, January 1 of 2001, 00:00 UTC';
            ?>
            <?php if ($showMetadata): ?>
            <div class="post-metadata">
                <?= \Indieinabox\Helper::kindLink($page, $page->kind) ?>
                <?php if (!in_array($page->kind, ['generic', 'home', 'page'], true)): ?>• <?php endif; ?><a href="<?= $page->relpath ?><?= $page->slug ?>" class="u-url"><time class="dt-published" datetime="<?= $page->isodate ?>"><?= $page->localizeddate ?></time></a>
                <?php if (!empty($page->tags)): ?>
                    •
                    <?php foreach ($page->tags as $tag): ?>
                        <a href="<?= $page->relpath ?>tag/<?= $tag ?>/" class="p-category">#<?= htmlspecialchars($tag) ?></a>&#32;
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($page->kind === 'jardim'): ?>
                    <?php if (isset($page->metadata->maturity)): ?>
                        • <?= \Indieinabox\Helper::translate('Maturity') ?>: <?= htmlspecialchars($page->metadata->maturity) ?>
                    <?php endif; ?>
                    <?php if (isset($page->metadata->reliability)): ?>
                        • <?= \Indieinabox\Helper::translate('Reliability') ?>: <?= htmlspecialchars($page->metadata->reliability) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="e-content" style="margin-left: 2em;">
                <?php if (isset($page->metadata->translated_by_ia) && $page->metadata->translated_by_ia !== false): ?>
                    <div class="ai-translation-notice" style="background: rgba(0,0,0,0.05); padding: 1em; border-left: 4px solid var(--accent); margin-bottom: 2em; font-size: 0.9em; font-style: italic;">
                        <?php if ($page->metadata->translated_by_ia === 'revised'): ?>
                            ✓ <?= \Indieinabox\Helper::translate('This page was automatically translated by AI and revised by a human.') ?>
                        <?php else: ?>
                            ⚠ <?= \Indieinabox\Helper::translate('This page was automatically translated by AI.') ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?= $page->content ?>
            </div>
        </article>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
