<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */

// Dynamic color matching
$kind = strtolower($page->kind ?? 'generic');
$layout = strtolower($page->layout ?? 'page');

$bg = '#F4F1EA';
$fg = '#2C2E2F';

if (in_array($kind, ['article', 'artigos', 'articles']) || in_array($layout, ['article', 'artigos', 'articles'])) {
    $bg = '#FDF6E3'; $fg = '#3A2E2A';
} elseif (in_array($kind, ['note', 'notas', 'notes']) || in_array($layout, ['note', 'notas', 'notes'])) {
    $bg = '#E8EDE7'; $fg = '#2A3B2C';
} elseif (in_array($kind, ['photo', 'fotos', 'photos']) || in_array($layout, ['photo', 'fotos', 'photos'])) {
    $bg = '#E6EDF2'; $fg = '#1C3A5A';
} elseif (in_array($kind, ['jardim', 'garden', 'pensamentos']) || in_array($layout, ['jardim', 'garden', 'pensamentos'])) {
    $bg = '#F0EAE1'; $fg = '#5C3A21';
}
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<meta name="generator" content="Indieinabox v0.1.0" />
<title><?= empty($page->title) || $page->title == "Untitled" ? $site->metadata->author : $page->title . " | " . $site->metadata->author ?></title>
<meta name="description" content="<?= htmlspecialchars($page->title) ?>">
<meta name="author" content="<?= htmlspecialchars($site->metadata->author) ?>">
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
    .header-divider, .footer-divider {
        color: var(--fg);
        margin: 1em 0;
        letter-spacing: -1px;
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
</style>
<?php if (isset($site->options->dev) && $site->options->dev): ?>
    <script src="<?= $page->relpath ?>js/live.js"></script>
<?php endif; ?>
