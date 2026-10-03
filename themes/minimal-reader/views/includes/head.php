<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page->title ?? $site->metadata->sitename) ?></title>
<link rel="stylesheet" href="<?= $page->relpath ?>css/style.css">
<?= \Indieinabox\Theme\ThemeData::getMetaTags($page, $site) ?>
