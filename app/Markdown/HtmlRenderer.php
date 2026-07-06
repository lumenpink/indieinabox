<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

/**
 * Class HtmlRenderer
 */
class HtmlRenderer implements RendererInterface
{
    /**
     * @var \Indieinabox\Page|null
     */
    private ?\Indieinabox\Page $page = null;

    /**
     * Set active page context.
     *
     * @param \Indieinabox\Page $page
     * @return void
     */
    public function setPage(\Indieinabox\Page $page): void
    {
        $this->page = $page;
    }

    /**
     * Map active layout / kind to appropriate background and foreground colors.
     *
     * @return array{bg: int[], fg: int[]}
     */
    private function getColors(): array
    {
        $kind = strtolower($this->page ? $this->page->kind : 'generic');
        $kindConfig = \Indieinabox\Helper::getKindConfig($kind);

        if (!empty($kindConfig['palette'])) {
            $bgHex = $kindConfig['palette']['bg'] ?? '#F4F1EA';
            $fgHex = $kindConfig['palette']['fg'] ?? '#2C2E2F';
            return [
                'bg' => sscanf($bgHex, "#%02x%02x%02x"),
                'fg' => sscanf($fgHex, "#%02x%02x%02x"),
            ];
        }

        // Global default
        return [
            'bg' => [244, 241, 234], // #F4F1EA
            'fg' => [44, 46, 47],    // #2C2E2F
        ];
    }

    /**
     * Recursively walks the AST and returns the generated HTML.
     *
     * @param Node $node
     * @return string
     */
    public function render(Node $node): string
    {
        if ($node instanceof RootNode) {
            $html = '';
            foreach ($node->children as $child) {
                $html .= $this->render($child);
            }
            return $html;
        }

        if ($node instanceof HeadingNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            if ($node->level === 1) {
                return "<h1 class=\"p-name\">{$inner}</h1>\n";
            }
            return "<h{$node->level}>{$inner}</h{$node->level}>\n";
        }

        if ($node instanceof ParagraphNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            return "<p>{$inner}</p>\n";
        }

        if ($node instanceof ListNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            return "<ul>\n{$inner}</ul>\n";
        }

        if ($node instanceof ListItemNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            return "  <li>{$inner}</li>\n";
        }

        if ($node instanceof TextNode) {
            return htmlspecialchars($node->text, ENT_QUOTES | ENT_HTML5);
        }

        if ($node instanceof StrongNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            return "<strong>{$inner}</strong>";
        }

        if ($node instanceof EmphasisNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->render($child);
            }
            return "<em>{$inner}</em>";
        }

        if ($node instanceof CodeInlineNode) {
            return "<code>" . htmlspecialchars($node->code, ENT_QUOTES | ENT_HTML5) . "</code>";
        }

        if ($node instanceof WikilinkNode) {
            $slug = \Indieinabox\Helper::slugize($node->target);
            $relpath = $this->page ? $this->page->relpath : './';
            $url = $relpath . 'jardim/' . $slug . '/';
            $urlEsc = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5);
            $labelEsc = htmlspecialchars($node->label, ENT_QUOTES | ENT_HTML5);
            return "<a href=\"{$urlEsc}\">{$labelEsc}</a>";
        }

        if ($node instanceof LinkNode) {
            $target = $node->target;
            if (preg_match('#^https?://#i', $target)) {
                global $site;
                $fqdn = $site?->metadata?->fqdn ?? '';
                if ($fqdn === '' || strpos($target, $fqdn) !== 0) {
                    $ts = $this->page && current($this->page->frontmatter) && isset($this->page->frontmatter['published']) 
                          ? strtotime((string)$this->page->frontmatter['published']) 
                          : time();
                    
                    // Fallback to page date if published not found but Date object exists
                    if ($this->page && $this->page->published && $ts === time()) {
                        $ts = $this->page->published->getTimestamp();
                    }
                    $target = '/archive?url=' . urlencode($target) . '&ts=' . $ts;
                }
            }
            $targetEsc = htmlspecialchars($target, ENT_QUOTES | ENT_HTML5);
            $labelEsc = htmlspecialchars($node->label, ENT_QUOTES | ENT_HTML5);
            return "<a href=\"{$targetEsc}\">{$labelEsc}</a>";
        }

        if ($node instanceof ImageNode) {
            $target = $node->target;
            $alt = $node->label;

            if ($this->page && $this->page->filepath && !preg_match('#^(https?:)?//#i', $target)) {
                $markdownFileDir = dirname($this->page->filepath);
                
                if (str_starts_with($target, '/')) {
                    global $site;
                    $base = $site?->paths?->baseDir ?? dirname(dirname(__DIR__));
                    $contentDir = $site?->paths?->contentDir ?? 'content';
                    $caminhoOriginal = $base . DIRECTORY_SEPARATOR . $contentDir . DIRECTORY_SEPARATOR . ltrim($target, '/');
                } else {
                    $caminhoOriginal = $markdownFileDir . DIRECTORY_SEPARATOR . $target;
                }

                if (file_exists($caminhoOriginal)) {
                    global $site;
                    $base = $site?->paths?->baseDir ?? dirname(dirname(__DIR__));
                    $outputDirHtml = $site?->paths?->outputDirHtml ?? 'public_html';

                    $pathInfo = pathinfo($target);
                    $gifName = $pathInfo['filename'] . '.gif';

                    $slug = $this->page->slug;
                    if (str_ends_with($slug, '.html')) {
                        $outputHtmlDir = dirname($base . DIRECTORY_SEPARATOR . $outputDirHtml . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($slug, '/')));
                    } else {
                        $outputHtmlDir = $base . DIRECTORY_SEPARATOR . $outputDirHtml . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($slug, '/'));
                    }

                    if (!is_dir($outputHtmlDir)) {
                        @mkdir($outputHtmlDir, 0777, true);
                    }

                    $caminhoDestino = $outputHtmlDir . DIRECTORY_SEPARATOR . $gifName;

                    $colors = $this->getColors();
                    $corBG = $colors['bg'];
                    $corFG = $colors['fg'];

                    $globalColors = [
                        'bg' => [244, 241, 234], // #F4F1EA
                        'fg' => [44, 46, 47],    // #2C2E2F
                    ];

                    $gifNameGlobal = $pathInfo['filename'] . '_global.gif';
                    $caminhoDestinoGlobal = $outputHtmlDir . DIRECTORY_SEPARATOR . $gifNameGlobal;

                    \Indieinabox\Helper::ditherImageToGif(
                        $caminhoOriginal,
                        $caminhoDestinoGlobal,
                        512,
                        $globalColors['bg'],
                        $globalColors['fg'],
                        true
                    );

                    $gifNameThumb = $pathInfo['filename'] . '_thumb.gif';
                    $caminhoDestinoThumb = $outputHtmlDir . DIRECTORY_SEPARATOR . $gifNameThumb;

                    \Indieinabox\Helper::createThumbnail(
                        $caminhoOriginal,
                        $caminhoDestinoThumb,
                        64,
                        $globalColors['bg'],
                        $globalColors['fg']
                    );

                    $success = \Indieinabox\Helper::ditherImageToGif(
                        $caminhoOriginal,
                        $caminhoDestino,
                        512,
                        $corBG,
                        $corFG,
                        true
                    );

                    if ($success) {
                        // Build a root-relative src so the image loads correctly
                        // from any page that embeds this content (e.g. home summary).
                        // e.g. slug = photos/my-first-photo.html → dir = photos/
                        //      slug = photos/my-first-photo/      → dir = photos/my-first-photo/
                        $slugTrimmed = trim($slug, '/');
                        if (str_ends_with($slug, '.html')) {
                            $slugDir = dirname($slugTrimmed);   // "photos"
                        } else {
                            $slugDir = $slugTrimmed;            // "photos/my-first-photo"
                        }
                        $target = '/' . ltrim($slugDir . '/' . $gifName, '/');

                        // Copy the original image to the public directory so we can link to it
                        $originalDestino = $outputHtmlDir . DIRECTORY_SEPARATOR . basename($caminhoOriginal);
                        if (!file_exists($originalDestino)) {
                            copy($caminhoOriginal, $originalDestino);
                        }
                        $originalTarget = '/' . ltrim($slugDir . '/' . basename($caminhoOriginal), '/');
                    }
                }
            }

            $targetEsc = htmlspecialchars($target, ENT_QUOTES | ENT_HTML5);
            $altEsc = htmlspecialchars($alt, ENT_QUOTES | ENT_HTML5);
            $imgTag = "<img src=\"{$targetEsc}\" alt=\"{$altEsc}\">";
            
            if (isset($originalTarget)) {
                $origEsc = htmlspecialchars($originalTarget, ENT_QUOTES | ENT_HTML5);
                return "<a href=\"{$origEsc}\" class=\"dithered-image-link\">{$imgTag}</a>\n";
            }
            return $imgTag . "\n";
        }
        return '';
    }
}
