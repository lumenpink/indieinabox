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
            <h1 class="p-name"><?= htmlspecialchars($page->title) ?></h1>
            
            <div class="post-metadata">
                <time class="dt-published" datetime="<?= $page->isodate ?>"><?= $page->localizeddate ?></time>
                <?php if ($page->kind === 'jardim'): ?>
                    <?php if (isset($page->metadata->maturity)): ?>
                        • <?= \Indieinabox\Helper::translate('Maturidade') ?>: <?= htmlspecialchars($page->metadata->maturity) ?>
                    <?php endif; ?>
                    <?php if (isset($page->metadata->reliability)): ?>
                        • <?= \Indieinabox\Helper::translate('Confiabilidade') ?>: <?= htmlspecialchars($page->metadata->reliability) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="e-content">
                <?= $page->content ?>
            </div>
        </article>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
