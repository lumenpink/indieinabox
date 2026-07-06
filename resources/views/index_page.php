<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
/** @var \Indieinabox\Pages $pages */
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
        
        <div class="sitemap-gopher">
            <p><?= \Indieinabox\Helper::translate('Browse the sections of the site in Gopher style:') ?></p>
            <ul style="list-style-type: none; padding-left: 0;">
                <?php
                // Get all non-draft pages
                $allPages = iterator_to_array($pages);
                $pages = iterator_to_array($pages);
                $pages = array_filter($pages, function($p) use ($page) {
                    return $p->lang === $page->lang && !in_array('draft', $p->tags);
                });

                // Sort globally first
                usort($pages, function($a, $b) {
                    $timeA = $a->date instanceof \DateTime ? $a->date->getTimestamp() : $a->date;
                    $timeB = $b->date instanceof \DateTime ? $b->date->getTimestamp() : $b->date;
                    return $timeB <=> $timeA;
                });

                // Group by kind, honoring the order in config.yml
                $grouped = [];
                if (!empty($site->config['kinds'])) {
                    foreach ($site->config['kinds'] as $k => $c) {
                        $grouped[$k] = [];
                    }
                }

                foreach ($pages as $p) {
                    if ($p->lang === $page->lang) {
                        if (!isset($grouped[$p->kind])) {
                            $grouped[$p->kind] = [];
                        }
                        $grouped[$p->kind][] = $p;
                    }
                }

                // Remove empty groups
                $grouped = array_filter($grouped);

                // Print groups
                foreach ($grouped as $kind => $list):
                ?>
                    <li style="margin-bottom: 1.5em;">
                        <strong><?= \Indieinabox\Helper::kindLink($page, $kind) ?></strong>
                        <ul style="list-style-type: none; padding-left: 20px; margin-top: 0.5em;">
                            <?php foreach ($list as $p): ?>
                                <li style="margin-bottom: 0.5em;">
                                    <?php 
                                        $displayMode = \Indieinabox\Helper::getKindConfig($p->kind)['display_mode'] ?? 'default';
                                    ?>
                                    <?php if ($displayMode === 'full_content'): ?>
                                        <div style="margin-bottom: 1em;">
                                            <div style="font-size:0.85em; opacity:0.75; margin-bottom: 0.5em;">=&gt; <a href="<?= $page->relpath ?><?= ltrim($p->slug, '/') ?>"><?= $p->localizeddate ?></a></div>
                                            <div style="border-left: 2px solid var(--fg); padding-left: 10px; margin-left: 10px;">
                                                <?php 
                                                    $content = $p->content;
                                                    $content = preg_replace('/src="([^"]+)\.gif"/', 'src="$1_global.gif"', (string)$content);
                                                    echo $content;
                                                ?>
                                            </div>
                                            <?php include('includes/interactions.php'); ?>
                                        </div>
                                    <?php elseif ($displayMode === 'thumbnail_snippet'): ?>
                                        <div style="margin-bottom: 1em; display: flex; align-items: flex-start; gap: 15px;">
                                            <?php
                                                $thumbSrc = '';
                                                if (preg_match('/src="([^"]+)\.gif"/', $p->content, $matches)) {
                                                    $thumbSrc = $matches[1] . '_thumb.gif';
                                                }
                                                $snippet = strip_tags($p->content);
                                                $snippet = trim(preg_replace('/\s+/', ' ', $snippet));
                                                if (mb_strlen($snippet) > 100) {
                                                    $snippet = mb_substr($snippet, 0, 97) . '...';
                                                }
                                            ?>
                                            <?php if ($thumbSrc): ?>
                                                <a href="<?= $page->relpath ?><?= ltrim($p->slug, '/') ?>">
                                                    <img src="<?= $thumbSrc ?>" alt="Thumbnail" style="width: 64px; height: 64px; object-fit: cover; border-radius: 4px; margin: 0;">
                                                </a>
                                            <?php else: ?>
                                                <div style="width: 64px; height: 64px; background: rgba(0,0,0,0.05); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.8em; opacity: 0.5;">img</div>
                                            <?php endif; ?>
                                            <div>
                                                <a href="<?= $page->relpath ?><?= ltrim($p->slug, '/') ?>" style="font-weight: bold; text-decoration: none;"><?= $p->localizeddate ?></a>
                                                <p style="margin: 0.2em 0 0 0; font-size: 0.9em; opacity: 0.9;"><?= $snippet ?></p>
                                                <?php include('includes/interactions.php'); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        =&gt; <a href="<?= $page->relpath ?><?= ltrim($p->slug, '/') ?>"><?= htmlspecialchars($p->title) ?></a>
                                        <span style="font-size:0.85em; opacity:0.75;">(<?= $p->localizeddate ?>)</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
