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
        <h1><?= htmlspecialchars($page->title) ?></h1>
        <?php
        $introFile = $site->paths->contentDir . '/' . $page->lang . '/intro.md';
        if (!file_exists($introFile)) {
            $introFile = $site->paths->contentDir . '/intro.md';
        }
        
        if (file_exists($introFile)) {
            echo '<div class="introduction" style="margin-left: 2em; margin-bottom: 5em;">';
            $processor = new \Indieinabox\Markdown\ContentProcessor();
            $rawIntro = file_get_contents($introFile);
            $cleanIntro = $processor->removeYamlFrontMatter($rawIntro);
            echo $processor->processContent($cleanIntro, $page);
            echo '</div><hr>';
        }
        ?>
        

        <h2><?= \Indieinabox\Helper::translate('Recent posts') ?></h2>
        <div class="catalogue h-feed">
            <?= \Indieinabox\Helper::listposts() ?>
        </div>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
