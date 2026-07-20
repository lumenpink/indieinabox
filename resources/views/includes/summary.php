<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
$_kindLabel = \Indieinabox\Helper::kindLabel($page->kind);
?>
<article class="h-entry the-summary" style="margin-bottom: 5em;">
    <header>
        <?php if (\Indieinabox\Helper::getKindConfig($page->kind)['has_title']): ?>
            <h3 style="margin: 0 0 0.5em 0;" class="p-name">
                <a href="<?= $page->relpath ?><?= $page->slug ?>" class="u-url" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($page->title) ?></a>
            </h3>
        <?php endif; ?>
        <div class="post-metadata" style="font-size: 0.85em; opacity: 0.8; margin-bottom: 1em;">
            <div class="meta-line-1">
                <?= \Indieinabox\Helper::kindLink($page, $page->kind) ?>
                <?php if (isset($page->date)): ?>
                    - <a href="<?= $page->relpath ?><?= $page->slug ?>" class="u-url"><time class="dt-published" datetime="<?= $page->isodate ?>"><?= $page->localizeddate ?></time></a>
                <?php endif; ?>
            </div>
            
            <?php
            $likes = \Indieinabox\Helper::getInteractions($page, 'like');
            $reposts = \Indieinabox\Helper::getInteractions($page, 'repost');
            $replies = \Indieinabox\Helper::getInteractions($page, 'reply');
            ?>
            <div class="meta-line-2" style="margin-left: 0.6em;">
                <?php if (!empty($page->shortlink)): ?>
                    <a href="<?= htmlspecialchars($page->shortlink) ?>" style="color: inherit; text-decoration: none; opacity: 0.8;">🔗</a> - 
                <?php endif; ?>
                
                <?php if (count($likes) > 0): ?>
                    <a href="<?= $page->relpath ?><?= $page->slug ?>/interactions#likes" style="color: inherit; text-decoration: none;">
                        <?= count($likes) ?> <?= \Indieinabox\Helper::translatePlural('Like', 'Likes', count($likes)) ?>
                    </a>
                <?php else: ?>
                    <span style="opacity: 0.8; font-size: 0.9em;">0 <?= \Indieinabox\Helper::translatePlural('Like', 'Likes', 0) ?></span>
                <?php endif; ?>
                /
                <?php if (count($reposts) > 0): ?>
                    <a href="<?= $page->relpath ?><?= $page->slug ?>/interactions#reposts" style="color: inherit; text-decoration: none;">
                        <?= count($reposts) ?> <?= \Indieinabox\Helper::translatePlural('Repost', 'Reposts', count($reposts)) ?>
                    </a>
                <?php else: ?>
                    <span style="opacity: 0.8; font-size: 0.9em;">0 <?= \Indieinabox\Helper::translatePlural('Repost', 'Reposts', 0) ?></span>
                <?php endif; ?>
                /
                <?php if (count($replies) > 0): ?>
                    <a href="<?= $page->relpath ?><?= $page->slug ?>#interactions" style="color: inherit; text-decoration: none;">
                        <?= count($replies) ?> <?= \Indieinabox\Helper::translatePlural('Reply', 'Replies', count($replies)) ?>
                    </a>
                <?php else: ?>
                    <span style="opacity: 0.8; font-size: 0.9em;">0 <?= \Indieinabox\Helper::translatePlural('Reply', 'Replies', 0) ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($page->tags)): ?>
                <div class="meta-line-3" style="margin-left: 0.6em;">
                    <?php foreach ($page->tags as $tag): ?>
                        <a href="<?= $page->relpath ?>tag/<?= $tag ?>/" class="p-category">#<?= htmlspecialchars($tag) ?></a>&#32;
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>
    <div class="e-content" style="margin-left: 2em;">
        <?php
        $content = $page->content;
        $content = preg_replace('/src="([^"]+)\.gif"/', 'src="$1_global.gif"', (string)$content);
        
        $kindConf = \Indieinabox\Helper::getKindConfig($page->kind);
        $hasTitle = $kindConf['has_title'] ?? true;
        
        $hasExcerpt = !empty($page->metadata->excerpt);
        $dontExcerpt = !empty($page->metadata->dont_excerpt);
        
        $readMoreLink = ' <a href="' . $page->relpath . ltrim($page->slug, '/') . '" class="u-url">' . \Indieinabox\Helper::translate('Read more') . '</a>';
        
        if ($dontExcerpt) {
            echo $content;
        } elseif ($hasExcerpt) {
            echo '<p>' . nl2br(htmlspecialchars($page->metadata->excerpt)) . '...' . $readMoreLink . '</p>';
        } else {
            $text = strip_tags($content);
            $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
            
            if (count($words) > 55) {
                $snippet = implode(' ', array_slice($words, 0, 55)) . '...';
                $needsReadMore = true;
            } else {
                $snippet = $text;
                $needsReadMore = false;
            }
            
            if (!$hasTitle) {
                $thumb = '';
                if (preg_match('/<img[^>]+src="([^"]+_global\.gif)"/i', $content, $matches)) {
                    $thumbSrc = str_replace('_global.gif', '_thumb.gif', $matches[1]);
                    $thumb = '<img src="' . htmlspecialchars($thumbSrc) . '" alt="Thumbnail" style="max-width:100px; float:left; margin-right:1em;">';
                }
                echo $thumb . '<p style="margin:0;">' . htmlspecialchars(trim($snippet)) . ($needsReadMore ? $readMoreLink : '') . '</p><div style="clear:both;"></div>';
            } else {
                if ($needsReadMore) {
                    echo '<p>' . htmlspecialchars(trim($snippet)) . $readMoreLink . '</p>';
                } else {
                    echo $content;
                }
            }
        }
        ?>
    </div>

    <?php if (!empty($page->metadata->syndication)): ?>
        <div class="syndication-links" style="margin-top: 1em; margin-left: 2em; font-size: 0.85em; opacity: 0.8;">
            <?= \Indieinabox\Helper::translate('Also on') ?>:
            <?php 
            $syndications = is_array($page->metadata->syndication) ? $page->metadata->syndication : [$page->metadata->syndication];
            foreach ($syndications as $synd):
            ?>
                <a href="<?= htmlspecialchars($synd) ?>" class="u-syndication" rel="syndication" style="margin-left: 0.5em;">
                    <?= htmlspecialchars(parse_url($synd, PHP_URL_HOST) ?? $synd) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
