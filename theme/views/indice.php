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
            <p><?= \Indieinabox\Helper::translate('Navegue pelas seções do site no estilo Gopher:') ?></p>
            <ul style="list-style-type: none; padding-left: 0;">
                <?php
                // Get all non-draft pages
                $allPages = iterator_to_array($pages);
                $allPages = array_filter($allPages, function($p) use ($page) {
                    return $p->lang === $page->lang && !in_array('draft', $p->tags) && $p->kind !== 'page' && $p->kind !== 'generic';
                });
                
                // Group by kind
                $grouped = [];
                foreach ($allPages as $p) {
                    $grouped[$p->kind][] = $p;
                }
                
                // Sort by date descending
                foreach ($grouped as $k => &$list) {
                    usort($list, function($a, $b) {
                        return $b->date->getTimestamp() <=> $a->date->getTimestamp();
                    });
                }
                unset($list);
                
                // Order groups: notas, fotos, artigos, jardim
                $order = ['note', 'photo', 'article', 'jardim'];
                uksort($grouped, function($a, $b) use ($order) {
                    $posA = array_search($a, $order);
                    $posB = array_search($b, $order);
                    if ($posA === false) $posA = 999;
                    if ($posB === false) $posB = 999;
                    return $posA <=> $posB;
                });

                // Print groups
                foreach ($grouped as $kind => $list):
                ?>
                    <li style="margin-bottom: 1.5em;">
                        <strong>[<?= strtoupper(\Indieinabox\Helper::kindLabel($kind)) ?>]</strong>
                        <ul style="list-style-type: none; padding-left: 20px; margin-top: 0.5em;">
                            <?php foreach ($list as $p): ?>
                                <li style="margin-bottom: 0.5em;">
                                    <?php if ($p->kind === 'note'): ?>
                                        <div style="margin-bottom: 1em;">
                                            <div style="font-size:0.85em; opacity:0.75; margin-bottom: 0.5em;">=&gt; <a href="<?= $p->relpath ?><?= $p->slug ?>"><?= $p->localizeddate ?></a></div>
                                            <div style="border-left: 2px solid var(--accent); padding-left: 10px; margin-left: 10px;">
                                                <?php 
                                                    $content = $p->content;
                                                    $content = preg_replace('/src="([^"]+)\.gif"/', 'src="$1_global.gif"', (string)$content);
                                                    echo $content;
                                                ?>
                                            </div>
                                        </div>
                                    <?php elseif ($p->kind === 'photo'): ?>
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
                                                <a href="<?= $p->relpath ?><?= $p->slug ?>">
                                                    <img src="<?= $thumbSrc ?>" alt="Thumbnail" style="width: 64px; height: 64px; object-fit: cover; border-radius: 4px; margin: 0;">
                                                </a>
                                            <?php else: ?>
                                                <div style="width: 64px; height: 64px; background: rgba(0,0,0,0.05); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.8em; opacity: 0.5;">img</div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-size:0.85em; opacity:0.75; margin-bottom: 0.25em;">=&gt; <a href="<?= $p->relpath ?><?= $p->slug ?>"><?= $p->localizeddate ?></a></div>
                                                <div style="font-size:0.95em;"><?= htmlspecialchars($snippet) ?></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        =&gt; <a href="<?= $p->relpath ?><?= $p->slug ?>"><?= htmlspecialchars($p->title) ?></a>
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
