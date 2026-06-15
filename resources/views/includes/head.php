    <?php
    /** @var \Indieinabox\Page $page */
    /** @var \Indieinabox\Pages $pages */
    /** @var \Indieinabox\Site $site */
    if ($site->dev) {

        echo "<!-- dev mode" . PHP_EOL;
        echo "************* site *************" . PHP_EOL;
        echo json_encode($site, JSON_PRETTY_PRINT);
        echo "************* page *************" . PHP_EOL;
        echo json_encode($page, JSON_PRETTY_PRINT);
        echo "************* pages *************" . PHP_EOL;
        echo json_encode($pages, JSON_PRETTY_PRINT);
        echo "-->";
    }
    ?>
    <meta name="generator" content="Indieinabox v0.1.0" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <?php if (!$site->dev): ?>
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self'; object-src 'none'" />
    <?php endif; ?>
    <meta property="og:title" content="~lumen" />
    <meta property="og:site_name" content="~lumen">
    <title><?= empty($page->title) || $page->title == "Untitled" ? $site->author : $page->title . " | " . $site->author ?></title>
    <meta property="og:description"
        content="Blog pessoal, notas e pensamentos de Lumen Pink" />
    <meta property="og:site_name" content="<?= empty($page->title) || $page->title == "Untitled" ? $site->author : $page->title . " | " . $site->author ?>">
    <meta property="og:url" content="https://lumen.pink/">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://lumen.pink/android-chrome-192x192.png" />
    <meta name="twitter:card" content="summary_large_image">
    <meta property="twitter:domain" content="lumen.pink">
    <meta property="twitter:url" content="https://lumen.pink/">
    <meta name="twitter:description" content="Blog pessoal, notas e pensamentos de Lumen Pink">
    <meta name="twitter:image" content="https://lumen.pink/android-chrome-192x192.png">
    <meta name="description" content="Blog pessoal, notas e pensamentos de Lumen Pink">
    <meta name="author" content="Lumen Pink">
    <meta name="language" content="<?= $page->lang ?>">
    <link rel="whostyle" href="whostyle.css">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $page->relpath ?>apple-touch-icon.png">
    <link rel="icon" type="image/svg+xml" href="<?= $page->relpath ?>favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $page->relpath ?>favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $page->relpath ?>favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="48x48" href="<?= $page->relpath ?>favicon.png">
    <?php

    if (is_array($page->otherlang)) {
        echo '<link rel="alternate" hreflang="' . $page->lang .
            '"href="' . $site->fqdn . "/" . ($page->slug == "/" ? "" : $page->slug) . '">' . PHP_EOL;

        foreach ($page->otherlang as $i => $lang) {
            echo '    <link rel="alternate" hreflang="' . $lang .
                ' "href="' . $site->fqdn . "/" . $page->otherlangpath[$i] . $page->langslug[$i] . '">' . PHP_EOL;
        }
    } ?>
    <link rel="manifest" href="<?= $page->relpath ?>site.webmanifest">
    <link rel="mask-icon" href="<?= $page->relpath ?>safari-pinned-tab.svg" color="#5bbad5">
    <meta name="apple-mobile-web-app-title" content="~lumen">
    <meta name="application-name" content="~lumen">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="theme-color" content="#eccb00">
    <link rel="feed" href="https://lumen.pink/">
    <link rel="me" href="https://social.lumen.pink/@j">
    <link rel="me" href="mailto:hi@lumen.pink">
    <link rel="me" href="https://github.com/lumenpink">
    <link rel="me" href="https://twitter.com/lumenpink">
    <link rel="authorization_endpoint" href=https://indieauth.com/auth />
    <link rel="token_endpoint" href=https://tokens.indieauth.com/token />
    <link rel="micropub" href="https://micropub.lumen.pink" />
    <link rel="microsub" href="https://aperture.p3k.io/microsub/795" />
    <link rel="webmention" href="https://webmention.io/lumen.pink/webmention" />
    <link rel="pingback" href="https://webmention.io/lumen.pink/xmlrpc" />
    <link rel="stylesheet" href="<?= $page->relpath ?>dist/app.css" />
    <?php if ($site->dev): ?>
        <script src="<?= $page->relpath ?>js/live.js"></script>
    <?php endif; ?>
    <script src="<?= $page->relpath ?>js/app.js"></script>