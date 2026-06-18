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
                
                // Print groups
                foreach ($grouped as $kind => $list):
                ?>
                    <li style="margin-bottom: 1.5em;">
                        <strong>[<?= strtoupper(\Indieinabox\Helper::translate($kind)) ?>]</strong>
                        <ul style="list-style-type: none; padding-left: 20px; margin-top: 0.5em;">
                            <?php foreach ($list as $p): ?>
                                <li style="margin-bottom: 0.5em;">
                                    =&gt; <a href="<?= $p->relpath ?><?= $p->slug ?>"><?= htmlspecialchars($p->title) ?></a>
                                    <span style="font-size:0.85em; opacity:0.75;">(<?= $p->localizeddate ?>)</span>
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
