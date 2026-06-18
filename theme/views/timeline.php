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
        <div class="notes-feed">
            <?= $page->content ?>
        </div>
    </main>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
