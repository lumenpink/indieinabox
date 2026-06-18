<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
$_kindLabel = \Indieinabox\Helper::kindLabel($page->kind);
?>
<article class="h-entry the-summary" style="margin-bottom: 2.5em;">
    <header>
        <?php if (!in_array($page->kind, ['note', 'photo'])): ?>
            <h3 style="margin: 0 0 0.5em 0;">
                <a href="<?= $page->relpath ?><?= $page->slug ?>"><?= htmlspecialchars($page->title) ?></a>
            </h3>
        <?php endif; ?>
        <div class="post-metadata" style="font-size: 0.85em; opacity: 0.8; margin-bottom: 1em;">
            [<?= strtoupper($_kindLabel) ?>]
            <?php if (isset($page->date)): ?>
                • <a href="<?= $page->relpath ?><?= $page->slug ?>"><time class="dt-published" datetime="<?= $page->isodate ?>"><?= $page->localizeddate ?></time></a>
            <?php endif; ?>
            <?php if (!empty($page->tags)): ?>
                •
                <?php foreach ($page->tags as $tag): ?>
                    <a href="<?= $page->relpath ?>tag/<?= $tag ?>/">#<?= htmlspecialchars($tag) ?></a>&#32;
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </header>
    <div class="e-content">
        <?php
        $content = $page->content;
        $content = preg_replace('/src="([^"]+)\.gif"/', 'src="$1_global.gif"', (string)$content);
        echo $content;
        ?>
    </div>
</article>
