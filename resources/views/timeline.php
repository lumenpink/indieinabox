<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
global $timeline, $mentions;
?>
<!DOCTYPE html>
<html lang="<?= $page->lang ?>">
<head>
    <?php include('includes/head.php'); ?>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <main>
        <h1><?= htmlspecialchars($page->title) ?></h1>
        
        <?php if (!empty($mentions)): ?>
            <section class="twtxt-mentions" style="margin-bottom: 3em;">
                <h2>Menções Recentes</h2>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach ($mentions as $mention): ?>
                        <li style="margin-bottom: 1em; padding: 1em; background: rgba(0,0,0,0.03); border-left: 4px solid var(--accent);">
                            <div style="font-size: 0.9em; opacity: 0.8; margin-bottom: 0.5em;">
                                <strong><?= htmlspecialchars($mention->nick) ?></strong> - 
                                <time datetime="<?= $mention->timestamp->format('c') ?>">
                                    <?= $mention->timestamp->format('Y-m-d H:i') ?>
                                </time>
                            </div>
                            <div class="twtxt-content">
                                <?= $mention->html ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="twtxt-timeline">
            <h2>Timeline</h2>
            <?php if (empty($timeline)): ?>
                <p>Sua timeline está vazia. Siga alguém para ver atualizações aqui!</p>
            <?php else: ?>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach ($timeline as $entry): ?>
                        <li style="margin-bottom: 1.5em; border-bottom: 1px dashed var(--accent); padding-bottom: 1.5em;">
                            <div style="font-size: 0.9em; opacity: 0.8; margin-bottom: 0.5em;">
                                <strong><?= htmlspecialchars($entry->nick) ?></strong> - 
                                <time datetime="<?= $entry->timestamp->format('c') ?>">
                                    <?= $entry->timestamp->format('Y-m-d H:i') ?>
                                </time>
                            </div>
                            <div class="twtxt-content">
                                <?= $entry->html ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        
        <div class="notes-feed">
            <?= $page->content ?>
        </div>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
