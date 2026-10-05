<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
use Indieinabox\Theme\ThemeData;

$colors = ThemeData::getThemeColors($page);
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<meta name="generator" content="Indieinabox v<?= htmlspecialchars(\Indieinabox\Core\Version::get()) ?>" />
<title><?= htmlspecialchars(ThemeData::getPageTitle($page, $site)) ?></title>
<?= ThemeData::getMetaTags($page, $site) ?>
<?= ThemeData::getOpenGraphTags($page, $site) ?>
<?= ThemeData::getTwitterCardTags($page, $site) ?>
<meta name="author" content="<?= htmlspecialchars($site->metadata->author ?? '') ?>">
<link rel="webmention" href="<?= rtrim($site->metadata->fqdn ?? '', '/') ?>/webmention">
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
        max-height: 75vh;
        width: auto;
        height: auto;
        object-fit: contain;
        display: block;
        margin: 1.5em auto;
    }
    .content-media {
        margin: 1.5em auto;
        text-align: center;
        max-width: 100%;
    }
    .content-media figcaption {
        text-align: center;
        margin-top: 0.5em;
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
    footer[role="contentinfo"] {
        margin-top: 3em;
        padding-top: 1.5em;
        text-align: center;
        font-size: 0.9em;
        opacity: 0.85;
        clear: both;
    }
    footer[role="contentinfo"] nav.footer-links {
        margin-bottom: 0.8em;
    }
    footer[role="contentinfo"] nav.footer-links a {
        color: var(--accent);
        text-decoration: underline;
        margin: 0 0.4em;
    }
    footer[role="contentinfo"] .footer-canonical {
        margin-top: 1em;
        line-height: 1.6;
        font-size: 0.9em;
    }
    footer[role="contentinfo"] .footer-line-identity {
        font-weight: 500;
        margin-bottom: 0.3em;
    }
    footer[role="contentinfo"] .footer-line-metadata {
        font-size: 0.85em;
        opacity: 0.8;
    }
    /* MediaWiki style diffs & versioning */
    .wiki-history-wrapper {
        margin: 1.5em 0;
    }
    .wiki-history-list {
        margin: 1em 0;
        padding-left: 0;
        list-style: none;
    }
    .wiki-history-item {
        padding: 0.4em 0;
        border-bottom: 1px dotted var(--border-color, rgba(128,128,128,0.3));
        font-family: inherit;
        font-size: 0.95em;
    }
    .mw-history-radios {
        margin-right: 0.5em;
    }
    .bytes-positive {
        color: #28a745;
        font-weight: 600;
    }
    .bytes-negative {
        color: #d73a49;
        font-weight: 600;
    }
    .bytes-neutral {
        opacity: 0.7;
    }
    table.diff {
        width: 100%;
        border-collapse: collapse;
        font-family: monospace;
        font-size: 0.88em;
        margin: 1.5em 0;
        border: 1px solid var(--border-color, rgba(128,128,128,0.3));
    }
    table.diff th {
        padding: 0.6em;
        text-align: left;
        background: rgba(128, 128, 128, 0.1);
        border-bottom: 2px solid var(--border-color, rgba(128,128,128,0.3));
    }
    table.diff td {
        padding: 0.25em 0.5em;
        vertical-align: top;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .diff-marker {
        width: 1.5em;
        text-align: center;
        font-weight: bold;
        user-select: none;
    }
    .diff-marker-del {
        color: #d73a49;
    }
    .diff-marker-add {
        color: #28a745;
    }
    .diff-deletedline {
        background-color: rgba(215, 58, 73, 0.15);
    }
    .diff-deletedline del {
        text-decoration: none;
        background-color: rgba(215, 58, 73, 0.25);
        border-radius: 2px;
    }
    .diff-addedline {
        background-color: rgba(40, 167, 69, 0.15);
    }
    .diff-addedline ins {
        text-decoration: none;
        background-color: rgba(40, 167, 69, 0.25);
        border-radius: 2px;
    }
    .diff-context {
        background-color: transparent;
        opacity: 0.8;
    }
    .wiki-revision-notice {
        margin-bottom: 1.5em;
        padding: 1em;
        border-left: 4px solid var(--accent, #e36209);
        background: rgba(227, 98, 9, 0.1);
        border-radius: 4px;
    }
</style>
