<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
/** @var array $reply */
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
            <h1><?= \Indieinabox\Helper::translate('Reply to') ?>: <a class="u-in-reply-to" href="<?= $page->relpath ?><?= $page->slug ?>"><?= htmlspecialchars($page->title) ?></a></h1>

            <div class="p-comment h-cite" style="margin-top: 2em; padding: 1em; border-left: 4px solid var(--accent); background: rgba(0,0,0,0.02);">
                <div style="margin-bottom: 1em;">
                    <strong>
                        <a class="p-author h-card u-url" href="<?= htmlspecialchars($reply['url']) ?>">
                            <?= htmlspecialchars($reply['author_name'] ?? $reply['url']) ?>
                        </a>
                    </strong>
                    <?php if (!empty($reply['published'])): ?>
                        <span style="opacity: 0.7; font-size: 0.9em; margin-left: 10px;">
                            <time class="dt-published" datetime="<?= date('c', $reply['published']) ?>">
                                <?= date('Y-m-d H:i', $reply['published']) ?>
                            </time>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="p-content e-content">
                    <?= nl2br(htmlspecialchars($reply['interaction_content'] ?? '')) ?>
                </div>
            </div>
            
            <div style="margin-top: 2em; font-size: 0.9em;">
                <a href="<?= $page->relpath ?><?= $page->slug ?>">&larr; <?= \Indieinabox\Helper::translate('Back to original post') ?></a>
            </div>
        </article>
    </main>
    
    <?php \Indieinabox\ThemeManager::includeView('includes/footer.php', get_defined_vars()); ?>
</body>
</html>
