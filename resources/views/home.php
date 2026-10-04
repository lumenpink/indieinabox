<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
?>
<!DOCTYPE html>
<html lang="<?= $page->lang ?>">
<head>
    <?php \Indieinabox\Theme\ThemeManager::includeView('includes/head.php', get_defined_vars()); ?>
</head>
<body>
    <?php \Indieinabox\Theme\ThemeManager::includeView('includes/header.php', get_defined_vars()); ?>
    
    <main>

        <?php
        $introRendered = null;
        if (isset($pages)) {
            foreach ($pages as $p) {
                if (($p->nick === 'intro' || ($p->filepath && basename($p->filepath) === 'intro.md')) && ($p->lang ?? '') === $page->lang) {
                    $introRendered = (string) ($p->content->content ?? '');
                    break;
                }
            }
        }

        if ($introRendered === null || $introRendered === '') {
            $contentPath = method_exists($site->paths, 'getContentPath') ? $site->paths->getContentPath() : $site->paths->contentDir;
            $introFile = $contentPath . '/' . $page->lang . '/intro.md';
            if (!file_exists($introFile)) {
                $introFile = $contentPath . '/intro.md';
            }
            if (file_exists($introFile)) {
                $processor = new \Indieinabox\Markdown\ContentProcessor();
                $rawIntro = (string) file_get_contents($introFile);
                $cleanIntro = $processor->removeYamlFrontMatter($rawIntro);
                $introRendered = $processor->processContent($cleanIntro, $page);
            }
        }

        if (!empty($introRendered) && trim($introRendered) !== '') {
            echo '<div class="introduction" style="margin-left: 2em;">';
            echo $introRendered;
            echo '</div><hr>';
        }
        ?>
        

        <?= \Indieinabox\Theme\ThemeData::getHCard($site) ?>

        <h2><?= \Indieinabox\Localization\Translator::translate('Recent posts') ?></h2>
        <div class="catalogue h-feed">
            <?= \Indieinabox\Taxonomy\KindHelper::listposts($pages ?? null, $site ?? null, $page ?? null) ?>
        </div>
    </main>
    
    <?php \Indieinabox\Theme\ThemeManager::includeView('includes/footer.php', get_defined_vars()); ?>
</body>
</html>
