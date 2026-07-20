<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
/** @var \Indieinabox\Pages $pages */
?>
<!DOCTYPE html>
<html lang="<?= $page->lang ?>">
<head>
    <?php \Indieinabox\ThemeManager::includeView('includes/head.php', get_defined_vars()); ?>
</head>
<body>
    <?php \Indieinabox\ThemeManager::includeView('includes/header.php', get_defined_vars()); ?>
    
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
                                    <?= \Indieinabox\Theme\ThemeHelper::renderPostSnippet($page, $p) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </main>
    
    <?php \Indieinabox\ThemeManager::includeView('includes/footer.php', get_defined_vars()); ?>
</body>
</html>
