<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

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
        $layout = strtolower($this->page ? $this->page->layout : 'page');

        if (in_array($kind, ['article', 'artigos', 'articles']) || in_array($layout, ['article', 'artigos', 'articles'])) {
            return [
                'bg' => [253, 246, 227], // #FDF6E3
                'fg' => [58, 46, 42],    // #3A2E2A
            ];
        }
        if (in_array($kind, ['note', 'notas', 'notes']) || in_array($layout, ['note', 'notas', 'notes'])) {
            return [
                'bg' => [232, 237, 231], // #E8EDE7
                'fg' => [42, 59, 44],    // #2A3B2C
            ];
        }
        if (in_array($kind, ['photo', 'fotos', 'photos']) || in_array($layout, ['photo', 'fotos', 'photos'])) {
            return [
                'bg' => [230, 237, 242], // #E6EDF2
                'fg' => [28, 58, 90],    // #1C3A5A
            ];
        }
        if (in_array($kind, ['jardim', 'garden', 'pensamentos']) || in_array($layout, ['jardim', 'garden', 'pensamentos'])) {
            return [
                'bg' => [240, 234, 225], // #F0EAE1
                'fg' => [92, 58, 33],    // #5C3A21
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
            $targetEsc = htmlspecialchars($node->target, ENT_QUOTES | ENT_HTML5);
            $labelEsc = htmlspecialchars($node->label, ENT_QUOTES | ENT_HTML5);
            return "<a href=\"{$targetEsc}\">{$labelEsc}</a>";
        }

        if ($node instanceof ImageNode) {
            $target = $node->target;
            $alt = $node->label;

            if ($this->page && $this->page->filepath && !preg_match('#^(https?:)?//#i', $target) && !str_starts_with($target, '/')) {
                $markdownFileDir = dirname($this->page->filepath);
                $caminhoOriginal = $markdownFileDir . DIRECTORY_SEPARATOR . $target;

                if (file_exists($caminhoOriginal)) {
                    global $site;
                    $base = $site?->paths?->baseDir ?? dirname(dirname(__DIR__));
                    $outputDir = $site?->paths?->outputDir ?? 'public';

                    $pathInfo = pathinfo($target);
                    $gifName = $pathInfo['filename'] . '.gif';

                    $slug = $this->page->slug;
                    if (str_ends_with($slug, '.html')) {
                        $outputHtmlDir = dirname($base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($slug, '/')));
                    } else {
                        $outputHtmlDir = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($slug, '/'));
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
                    }
                }
            }

            $targetEsc = htmlspecialchars($target, ENT_QUOTES | ENT_HTML5);
            $altEsc = htmlspecialchars($alt, ENT_QUOTES | ENT_HTML5);
            return "<img src=\"{$targetEsc}\" alt=\"{$altEsc}\">\n";
        }

        return '';
    }
}
