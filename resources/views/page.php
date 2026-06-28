<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
/** @var string $base */
/** @var string $themeDir */
?>
<!DOCTYPE html>
<html lang="<?= $page->lang ?>">

<head>
    <?php \Indieinabox\ThemeManager::loadView($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . 'views/includes/head.php', get_defined_vars()); //NOSONAR 
    ?>
</head>

<body>
    <?php \Indieinabox\ThemeManager::loadView($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . 'views/includes/header.php', get_defined_vars()); //NOSONAR
    ?>
    <div id="content">
        <?php \Indieinabox\ThemeManager::loadView($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . 'views/includes/summary.php', get_defined_vars()); //NOSONAR
        ?>
    </div>
    <?php \Indieinabox\ThemeManager::loadView($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . 'views/includes/footer.php', get_defined_vars()); //NOSONAR 
    ?>
</body>

</html>