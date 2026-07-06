<?php
/** @var \Indieinabox\Page $page */
global $footerLinks;
?>
<footer role="contentinfo">
    <hr>
    <nav class="footer-links" aria-label="Footer navigation" style="text-align: center;">
        <?php
        $linksHTML = [];
        if (!empty($footerLinks)) {
            foreach ($footerLinks as $item) {
                $linksHTML[] = '<a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['label']) . '</a>';
            }
        }
        $linksHTML[] = '<a href="' . $page->relpath . 'rss.xml">RSS</a>';
        $linksHTML[] = '<a href="' . $page->relpath . 'atom.xml">ATOM</a>';
        echo implode(' | ', $linksHTML);
        ?>
    </nav>
</footer>

<?php
$seo = \Indieinabox\Helper::getSeoMetadata($page);
global $site;
$baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
$pageUrl = $baseUrl . '/' . ltrim($page->relpath ?? '', '/');

$imageInfo = pathinfo($seo['image']);
$baseImageName = $imageInfo['dirname'] . '/' . $imageInfo['filename'];
$img16x9 = $baseImageName . '_1920x1080.png';
$img4x3 = $baseImageName . '_1440x1080.png';
$img1x1 = $baseImageName . '_1080x1080.png';

$jsonLd = [
    "@context" => "https://schema.org",
    "@type" => $seo['schema_type'],
    "mainEntityOfPage" => [
        "@type" => "WebPage",
        "@id" => $pageUrl
    ],
    "headline" => empty($page->title) || $page->title == "Untitled" ? ($site->metadata->author ?? '') : $page->title,
    "description" => $seo['description'],
    "image" => [
        $img16x9,
        $img4x3,
        $img1x1
    ],
    "datePublished" => $page->isodate ?? date('c'),
    "dateModified" => $page->isodate ?? date('c'),
    "author" => [
        "@type" => "Person",
        "name" => $site->metadata->author ?? 'Author',
        "url" => $baseUrl
    ],
    "publisher" => [
        "@type" => "Organization",
        "name" => $site->metadata->author ?? 'Blog',
        "logo" => [
            "@type" => "ImageObject",
            "url" => $baseUrl . "/media/default.png"
        ]
    ]
];
?>
<script type="application/ld+json">
<?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
