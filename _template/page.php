<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
?>
<!DOCTYPE html>
<html lang="<?= $page->lang ?>">

<head>
    <?php include 'includes/head.php';  //NOSONAR 
    ?>
</head>

<body>
    <?php include 'includes/header.php'; //NOSONAR
    ?>
    <div id="content">
        <?php include 'includes/summary.php'; //NOSONAR
        ?>
    </div>
    <?php include 'includes/footer.php'; //NOSONAR 
    ?>
</body>

</html>