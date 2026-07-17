<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
use Indieinabox\Theme\ThemeData;

$colors = ThemeData::getThemeColors($page);
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<meta name="generator" content="Indieinabox v0.1.0" />
<title><?= htmlspecialchars(ThemeData::getPageTitle($page, $site)) ?></title>
<?= ThemeData::getMetaTags($page, $site) ?>
<?= ThemeData::getOpenGraphTags($page, $site) ?>
<?= ThemeData::getTwitterCardTags($page, $site) ?>
<meta name="author" content="<?= htmlspecialchars($site->metadata->author ?? '') ?>">
<link rel="microsub" href="<?= rtrim($site->metadata->fqdn ?? '', '/') ?>/microsub">
<link rel="alternate" type="application/rss+xml" title="RSS Feed" href="<?= $page->relpath ?>rss.xml">
<link rel="alternate" type="application/atom+xml" title="Atom Feed" href="<?= $page->relpath ?>atom.xml">
<style>
    :root {
        --bg: <?= $colors['bg'] ?>;
        --fg: <?= $colors['fg'] ?>;
        --accent: <?= $colors['fg'] ?>;
    }
    body {
        background-color: var(--bg);
        color: var(--fg);
        font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        line-height: 1.6;
        max-width: 650px;
        margin: 40px auto;
        padding: 0 16px;
    }
    a {
        color: var(--accent);
        text-decoration: underline;
    }
    a:hover, a:focus-visible {
        text-decoration: none;
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }
    hr {
        border: none;
        border-top: 1px dashed var(--fg);
        margin: 2em 0;
    }
    hr.divisor-bloco {
        border-top: 1px dashed var(--accent);
    }
    pre, code {
        background: rgba(0, 0, 0, 0.05);
        padding: 2px 4px;
        font-size: 0.9em;
    }
    pre {
        padding: 1em;
        overflow-x: auto;
        display: block;
    }
    img {
        max-width: 100%;
        height: auto;
        display: block;
        margin: 1.5em 0;
    }
    .post-meta {
        font-size: 0.8em;
        color: var(--fg);
        opacity: 0.7;
        margin-bottom: 2em;
    }
    .nav-links {
        margin-top: 2em;
    }
    
    .logo-figlet {
        font-size: 10px;
        line-height: 10px;
        white-space: pre;
        text-align: center;
        margin-bottom: 2em;
    }
    
    .tag-link {
        display: inline-block;
        padding: 2px 6px;
        background: rgba(0, 0, 0, 0.05);
        border-radius: 4px;
        font-size: 0.85em;
        margin-right: 4px;
        margin-bottom: 4px;
        text-decoration: none;
        color: var(--fg);
        border: 1px dashed var(--accent);
    }
    .tag-link:hover {
        background: rgba(0, 0, 0, 0.1);
        text-decoration: none;
    }
</style>
