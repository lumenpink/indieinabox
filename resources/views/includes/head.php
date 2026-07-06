<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */

// Dynamic color matching
$kind = strtolower($page->kind ?? 'generic');
$layout = strtolower($page->layout ?? 'page');

$bg = '#F4F1EA';
$fg = '#2C2E2F';

$kindConfig = \Indieinabox\Helper::getKindConfig($kind);
if (!empty($kindConfig['palette'])) {
    $bg = $kindConfig['palette']['bg'] ?? $bg;
    $fg = $kindConfig['palette']['fg'] ?? $fg;
}

$seo = \Indieinabox\Helper::getSeoMetadata($page);
$baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
$pageUrl = $baseUrl . '/' . ltrim($page->relpath ?? '', '/');
$imageInfo = pathinfo($seo['image']);
$ogImage = $imageInfo['dirname'] . '/' . $imageInfo['filename'] . '_1200x630.png';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<meta name="generator" content="Indieinabox v0.1.0" />
<title><?= empty($page->title) || $page->title == "Untitled" ? $site->metadata->author : $page->title . " | " . $site->metadata->author ?></title>
<meta name="description" content="<?= htmlspecialchars($seo['description']) ?>">
<link rel="canonical" href="<?= htmlspecialchars($pageUrl) ?>">
<?php if (!empty($page->shortlink)): ?>
<link rel="shortlink" href="<?= htmlspecialchars($page->shortlink) ?>">
<?php endif; ?>

<meta property="og:site_name" content="<?= htmlspecialchars($site->metadata->author ?? 'Blog') ?>" />
<meta property="og:type" content="<?= htmlspecialchars(in_array($seo['schema_type'], ['BlogPosting', 'Article', 'SocialMediaPosting', 'Comment']) ? 'article' : 'website') ?>" />
<meta property="og:title" content="<?= empty($page->title) || $page->title == "Untitled" ? htmlspecialchars($site->metadata->author ?? '') : htmlspecialchars($page->title) ?>" />
<meta property="og:description" content="<?= htmlspecialchars($seo['description']) ?>" />
<meta property="og:url" content="<?= htmlspecialchars($pageUrl) ?>" />
<meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>" />
<meta property="og:image:alt" content="<?= htmlspecialchars($seo['image_alt']) ?>" />
<?php if (!empty($page->isodate)): ?>
<meta property="article:published_time" content="<?= htmlspecialchars($page->isodate) ?>" />
<?php endif; ?>
<meta property="article:author" content="<?= htmlspecialchars($site->metadata->author ?? '') ?>" />

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= empty($page->title) || $page->title == "Untitled" ? htmlspecialchars($site->metadata->author ?? '') : htmlspecialchars($page->title) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($seo['description']) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
<meta name="twitter:image:alt" content="<?= htmlspecialchars($seo['image_alt']) ?>">
<meta name="author" content="<?= htmlspecialchars($site->metadata->author) ?>">
<link rel="microsub" href="<?= rtrim($site->metadata->fqdn, '/') ?>/microsub">
<link rel="alternate" type="application/rss+xml" title="RSS Feed" href="<?= $page->relpath ?>rss.xml">
<link rel="alternate" type="application/atom+xml" title="Atom Feed" href="<?= $page->relpath ?>atom.xml">
<style>
    :root {
        --bg: <?= $bg ?>;
        --fg: <?= $fg ?>;
        --accent: <?= $fg ?>;
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
    a:hover {
        text-decoration: none;
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
    .lang-selector, .top-nav {
        margin: 0.5em 0;
        font-size: 0.95em;
    }
    .logo-figlet {
        text-align: center;
        font-size: 14px;
        line-height: 1.2;
        margin-bottom: 2em;
        white-space: pre;
        overflow-x: hidden;
        background: transparent;
        padding: 0;
    }
    @media (max-width: 600px) {
        .logo-figlet {
            font-size: 10px;
        }
    }
    .footer-links {
        margin-top: 2em;
        font-size: 0.9em;
    }
    h1, h2, h3, h4, h5, h6 {
        line-height: 1.2;
        margin-top: 1.5em;
        margin-bottom: 0.5em;
    }
    .post-metadata {
        font-size: 0.9em;
        opacity: 0.8;
        margin-bottom: 2em;
    }
    a {
        transition: color 0.15s ease-in-out;
    }
    a:focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
        border-radius: 2px;
    }
</style>
<?php if (isset($site->options->dev) && $site->options->dev): ?>
    <script src="<?= $page->relpath ?>js/live.js"></script>
<?php endif; ?>
