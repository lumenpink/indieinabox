<?php
/** @var \Indieinabox\Page $p */
$likes = \Indieinabox\Helper::getInteractions($p, 'like');
$reposts = \Indieinabox\Helper::getInteractions($p, 'repost');
$replies = \Indieinabox\Helper::getInteractions($p, 'reply');
?>
<?php if (count($likes) > 0 || count($reposts) > 0 || count($replies) > 0): ?>
    <div class="post-interactions" style="margin-top: 1em; border-top: 1px solid var(--accent); padding-top: 0.5em; font-size: 0.85em; opacity: 0.9;">
        <?php if (count($likes) > 0 || count($reposts) > 0): ?>
            <div style="margin-bottom: 1em;">
                <a href="<?= $p->relpath ?><?= $p->slug ?>/interactions" style="text-decoration: none; font-weight: bold;">
                    [ 
                    <?php if (count($likes) > 0): ?>
                        <?= count($likes) ?> <?= \Indieinabox\Helper::translate('Likes') ?> 
                    <?php endif; ?>
                    <?php if (count($likes) > 0 && count($reposts) > 0): ?> | <?php endif; ?>
                    <?php if (count($reposts) > 0): ?>
                        <?= count($reposts) ?> <?= \Indieinabox\Helper::translate('Reposts') ?> 
                    <?php endif; ?>
                    ]
                </a>
            </div>
        <?php endif; ?>

        <?php if (count($replies) > 0): ?>
            <div style="margin-top: 1.5em; width: 100%;">
                <h3 style="margin-bottom: 1em; font-size: 1.1em;"><?= count($replies) ?> <?= \Indieinabox\Helper::translate('Replies') ?></h3>
                <div style="margin-left: 0.5em;">
                    <?php foreach ($replies as $reply): ?>
                        <div class="p-comment h-cite" id="reply-<?= md5($reply['url']) ?>" style="margin-bottom: 1.5em; padding-left: 10px; border-left: 2px solid var(--accent);">
                            <div style="margin-bottom: 0.3em;">
                                <strong><a class="p-author h-card" href="<?= htmlspecialchars($reply['url']) ?>" rel="nofollow"><?= htmlspecialchars($reply['author_name']) ?></a></strong>
                                <a href="<?= $p->relpath ?><?= $p->slug ?>/reply/<?= md5($reply['url']) ?>" style="margin-left: 10px; font-size: 0.85em; opacity: 0.7;">
                                    <?= \Indieinabox\Helper::translate('Permalink') ?>
                                </a>
                            </div>
                            <div class="p-content" style="font-size: 0.95em; line-height: 1.4; opacity: 0.95;">
                                <?= nl2br(htmlspecialchars($reply['interaction_content'] ?? '')) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
