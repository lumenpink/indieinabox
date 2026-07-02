<?php
/** @var \Indieinabox\Page $page */
global $footerLinks;
?>
<footer>
    <hr>
    <div class="footer-links" style="text-align: center;">
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
    </div>
</footer>
