<!DOCTYPE html>
<html lang="<?= $page["lang"] ?>">

<head>
    <?php include('includes/head.php'); ?>
</head>

<body>
    <?php include('includes/header.php'); ?>
    <?php include('includes/introduction.' . $page["lang"] . '.php'); ?>
    <div class="catalogue h-feed">
        <?= listposts() ?>
        <!--
        {{ range where $items "Params.deleted" false }}
        {{ .Render "summary" }}
        {{ end }}        
        # TODO: include pagination
        template "_internal/pagination.html"
        -->
    </div>
    <?php include('includes/footer.php'); ?>
</body>

</html>