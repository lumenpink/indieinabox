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
            <h1><?= \Indieinabox\Helper::translate('Interactions on') ?>: <a href="<?= $page->relpath ?><?= $page->slug ?>"><?= htmlspecialchars($page->title) ?></a></h1>

            <?php if (count($likes) > 0): ?>
                <section id="likes" style="margin-top: 2em;">
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
                <section id="reposts" style="margin-top: 2em;">
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

            <?php if (count($replies) > 0): ?>
                <section id="replies" style="margin-top: 2em;">
                    <h2><?= count($replies) ?> <?= \Indieinabox\Helper::translate('Replies') ?></h2>
                    <div style="margin-left: 0.5em;">
                        <?php foreach ($replies as $reply): ?>
                            <div class="p-comment h-cite" id="reply-<?= md5($reply['url']) ?>" style="margin-bottom: 1.5em; padding-left: 10px; border-left: 2px solid var(--accent);">
                                <div style="margin-bottom: 0.3em;">
                                    <strong><a class="p-author h-card" href="<?= htmlspecialchars($reply['url']) ?>" rel="nofollow"><?= htmlspecialchars($reply['author_name'] ?? $reply['url']) ?></a></strong>
                                    <?php
                                    $baseDir = str_ends_with($page->slug, '.html') ? dirname($page->slug) : rtrim($page->slug, '/');
                                    if ($baseDir === '.' || $baseDir === '\\') $baseDir = '';
                                    $replyUrl = $page->relpath . ltrim($baseDir ? $baseDir . '/' : '', '/') . 'reply/' . md5($reply['url']) . '/';
                                    ?>
                                    <a href="<?= $replyUrl ?>" style="margin-left: 10px; font-size: 0.85em; opacity: 0.7;">
                                        <?= \Indieinabox\Helper::translate('Permalink') ?>
                                    </a>
                                </div>
                                <div class="p-content" style="font-size: 0.95em; line-height: 1.4; opacity: 0.95;">
                                    <?= nl2br(htmlspecialchars($reply['interaction_content'] ?? '')) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </article>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
