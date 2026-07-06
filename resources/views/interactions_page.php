<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
/** @var array $likes */
/** @var array $reposts */
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
            <h1>Interações em: <a href="<?= $page->relpath ?><?= $page->slug ?>"><?= htmlspecialchars($page->title) ?></a></h1>

            <?php if (count($likes) > 0): ?>
                <section style="margin-top: 2em;">
                    <h2><?= count($likes) ?> <?= \Indieinabox\Helper::translate('Likes') ?></h2>
                    <ul style="list-style: none; padding-left: 0;">
                        <?php foreach ($likes as $like): ?>
                            <li style="margin-bottom: 0.5em;">
                                <a class="u-url" href="<?= htmlspecialchars($like['url']) ?>" rel="nofollow">
                                    <?= htmlspecialchars($like['author_name'] ?? $like['url']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (count($reposts) > 0): ?>
                <section style="margin-top: 2em;">
                    <h2><?= count($reposts) ?> <?= \Indieinabox\Helper::translate('Reposts') ?></h2>
                    <ul style="list-style: none; padding-left: 0;">
                        <?php foreach ($reposts as $repost): ?>
                            <li style="margin-bottom: 0.5em;">
                                <a class="u-url" href="<?= htmlspecialchars($repost['url']) ?>" rel="nofollow">
                                    <?= htmlspecialchars($repost['author_name'] ?? $repost['url']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </article>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
