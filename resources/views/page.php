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
                <?php if ($page->kind === 'garden' || $page->kind === 'jardim'): ?>
                    <?php
                    $flowerbed = isset($page->metadata->flowerbed) && is_array($page->metadata->flowerbed) ? $page->metadata->flowerbed : ['general'];
                    $confidence = $page->metadata->confidence ?? 'possible';
                    $maturity = $page->metadata->maturity ?? 'sprout';
                    $importance = $page->metadata->importance ?? 'trivial';
                    
                    $translatedFlowerbed = array_map(function($fb) { return \Indieinabox\Helper::translate($fb); }, $flowerbed);
                    ?>
                    • <?= \Indieinabox\Helper::translate('Flowerbed') ?>: <?= htmlspecialchars(implode(', ', $translatedFlowerbed)) ?><br>
                    • <?= \Indieinabox\Helper::translate('Confidence') ?>: <?= htmlspecialchars(\Indieinabox\Helper::translate($confidence)) ?><br>
                    • <?= \Indieinabox\Helper::translate('Maturity') ?>: <?= htmlspecialchars(\Indieinabox\Helper::translate($maturity)) ?><br>
                    • <?= \Indieinabox\Helper::translate('Importance') ?>: <?= htmlspecialchars(\Indieinabox\Helper::translate($importance)) ?>
                <?php endif; ?>
                <?php if (!empty($page->shortlink)): ?>
                    • <?= \Indieinabox\Helper::translate('Shortlink') ?>: <a href="<?= htmlspecialchars($page->shortlink) ?>"><?= htmlspecialchars($page->shortlink) ?></a>
                <?php endif; ?>
                <?php
                $likes = \Indieinabox\Helper::getInteractions($page, 'like');
                $reposts = \Indieinabox\Helper::getInteractions($page, 'repost');
                $replies = \Indieinabox\Helper::getInteractions($page, 'reply');
                $totalInteractions = count($likes) + count($reposts) + count($replies);
                ?>
                • 
                <?php if (count($likes) > 0): ?>
                    <a href="<?= $page->relpath ?><?= $page->slug ?>/interactions#likes" style="color: inherit; text-decoration: none;">
                        <?= count($likes) ?> <?= \Indieinabox\Helper::translate('Likes') ?>
                    </a>
                <?php else: ?>
                    <span style="opacity: 0.8; font-size: 0.9em;">0 <?= \Indieinabox\Helper::translate('Likes') ?></span>
                <?php endif; ?>
                /
                <?php if (count($reposts) > 0): ?>
                    <a href="<?= $page->relpath ?><?= $page->slug ?>/interactions#reposts" style="color: inherit; text-decoration: none;">
                        <?= count($reposts) ?> <?= \Indieinabox\Helper::translate('Reposts') ?>
                    </a>
                <?php else: ?>
                    <span style="opacity: 0.8; font-size: 0.9em;">0 <?= \Indieinabox\Helper::translate('Reposts') ?></span>
                <?php endif; ?>
                /
                <?php if (count($replies) > 0): ?>
                    <a href="<?= $page->relpath ?><?= $page->slug ?>#interactions" style="color: inherit; text-decoration: none;">
                        <?= count($replies) ?> <?= \Indieinabox\Helper::translate('Replies') ?>
                    </a>
                <?php else: ?>
                    <span style="opacity: 0.8; font-size: 0.9em;">0 <?= \Indieinabox\Helper::translate('Replies') ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php
            $indiewebProps = [
                'in_reply_to' => ['class' => 'u-in-reply-to', 'label' => 'In reply to'],
                'like_of' => ['class' => 'u-like-of', 'label' => 'Liked'],
                'repost_of' => ['class' => 'u-repost-of', 'label' => 'Reposted'],
                'bookmark_of' => ['class' => 'u-bookmark-of', 'label' => 'Bookmarked'],
                'watch_of' => ['class' => 'u-watch-of', 'label' => 'Watched'],
                'read_of' => ['class' => 'u-read-of', 'label' => 'Read'],
                'listen_of' => ['class' => 'u-listen-of', 'label' => 'Listened to']
            ];
            ?>
            <div class="indieweb-context" style="margin-bottom: 1em; font-size: 0.9em; opacity: 0.8;">
                <?php foreach ($indiewebProps as $prop => $data): ?>
                    <?php if (!empty($page->metadata->$prop)): ?>
                        <div class="context-item">
                            <span class="context-label"><?= \Indieinabox\Helper::translate($data['label']) ?>:</span>
                            <a href="<?= htmlspecialchars($page->metadata->$prop ?? '') ?>" class="<?= $data['class'] ?>"><?= htmlspecialchars($page->metadata->$prop ?? '') ?></a>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!empty($page->metadata->rsvp)): ?>
                    <div class="context-item">
                        <span class="context-label">RSVP:</span>
                        <data class="p-rsvp" value="<?= htmlspecialchars($page->metadata->rsvp ?? '') ?>"><?= htmlspecialchars($page->metadata->rsvp ?? '') ?></data>
                    </div>
                <?php endif; ?>
            </div>

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

            <?php
            $p = $page;
            include('includes/interactions.php');
            ?>
        </article>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
