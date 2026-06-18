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
        <div class="introduction">
            <?php
            $introFile = __DIR__ . '/includes/introduction.' . $page->lang . '.php';
            if (file_exists($introFile)) {
                include($introFile);
            } else {
                echo "<p>Welcome to my static website.</p>";
            }
            ?>
        </div>
        
        <hr>
        
        <h2><?= \Indieinabox\Helper::translate('Publicações recentes') ?></h2>
        <div class="catalogue">
            <?= \Indieinabox\Helper::listposts() ?>
        </div>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
