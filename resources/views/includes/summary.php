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
            <?= \Indieinabox\Helper::kindLink($page, $page->kind) ?>
            <?php if (isset($page->date)): ?>
                • <a href="<?= $page->relpath ?><?= $page->slug ?>" class="u-url"><time class="dt-published" datetime="<?= $page->isodate ?>"><?= $page->localizeddate ?></time></a>
            <?php endif; ?>
            <?php if (!empty($page->tags)): ?>
                •
                <?php foreach ($page->tags as $tag): ?>
                    <a href="<?= $page->relpath ?>tag/<?= $tag ?>/" class="p-category">#<?= htmlspecialchars($tag) ?></a>&#32;
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($page->shortlink)): ?>
                • <a href="<?= htmlspecialchars($page->shortlink) ?>" style="color: inherit; text-decoration: none; opacity: 0.8;">🔗</a>
            <?php endif; ?>
            <?php
            $likes = \Indieinabox\Helper::getInteractions($page, 'like');
            $reposts = \Indieinabox\Helper::getInteractions($page, 'repost');
            $replies = \Indieinabox\Helper::getInteractions($page, 'reply');
            if (count($likes) > 0 || count($reposts) > 0 || count($replies) > 0):
            ?>
                • <a href="<?= $page->relpath ?><?= $page->slug ?>" style="color: inherit; font-weight: bold; text-decoration: none;">
                    <?php
                    $counts = [];
                    if (count($likes) > 0) $counts[] = count($likes) . ' ' . \Indieinabox\Helper::translate('Likes');
                    if (count($reposts) > 0) $counts[] = count($reposts) . ' ' . \Indieinabox\Helper::translate('Reposts');
                    if (count($replies) > 0) $counts[] = count($replies) . ' ' . \Indieinabox\Helper::translate('Replies');
                    echo implode(' / ', $counts);
                    ?>
                </a>
            <?php endif; ?>
        </div>
    </header>
    <div class="e-content" style="margin-left: 2em;">
        <?php
        $content = $page->content;
        $content = preg_replace('/src="([^"]+)\.gif"/', 'src="$1_global.gif"', (string)$content);
        
        $kindConf = \Indieinabox\Helper::getKindConfig($page->kind);
        if (isset($kindConf['has_title']) && !$kindConf['has_title']) {
            $text = strip_tags($content);
            $snippet = mb_strlen($text) > 200 ? mb_substr($text, 0, 200) . '...' : $text;
            
            $thumb = '';
            if (preg_match('/<img[^>]+src="([^"]+_global\.gif)"/i', $content, $matches)) {
                $thumbSrc = str_replace('_global.gif', '_thumb.gif', $matches[1]);
                $thumb = '<img src="' . htmlspecialchars($thumbSrc) . '" alt="Thumbnail" style="max-width:100px; float:left; margin-right:1em;">';
            }
            
            echo $thumb . '<p style="margin:0;">' . htmlspecialchars(trim($snippet)) . '</p><div style="clear:both;"></div>';
        } else {
            echo $content;
        }
        ?>
    </div>
</article>
<hr class="divisor-bloco">
