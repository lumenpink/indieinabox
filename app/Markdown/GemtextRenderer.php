<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

/**
 * Class GemtextRenderer
 */
class GemtextRenderer implements RendererInterface
{
    /**
     * @var array<array{target: string, label: string}>
     */
    private array $links = [];

    /**
     * @var \Indieinabox\Page|null
     */
    private ?\Indieinabox\Page $page = null;

    /**
     * Initializes the GemtextRenderer with the current page context.
     *
     * @param \Indieinabox\Page|null $page The page being rendered, used for resolving relative links.
     */
    public function __construct(?\Indieinabox\Page $page = null)
    {
        $this->page = $page;
    }

    /**
     * Renders a Node AST to Gemini/Gemtext format.
     *
     * @param Node $node
     * @return string
     */
    public function render(Node $node): string
    {
        $this->links = [];
        $body = $this->renderNode($node);
        $body = trim($body);

        if (empty($this->links)) {
            return $body . "\n";
        }

        // Append collected links at the end of the document
        $linksSection = "\n\n";
        foreach ($this->links as $link) {
            $linksSection .= "=> {$link['target']} {$link['label']}\n";
        }
        return $body . $linksSection;
    }

    /**
     * Recursively renders an AST node into Gemtext format.
     * Handles specific node types like headings, lists, quotes, and links.
     *
     * @param Node $node The AST node to render.
     * @return string The rendered Gemtext string.
     */
    private function renderNode(Node $node): string
    {
        if ($node instanceof RootNode) {
            $text = '';
            foreach ($node->children as $child) {
                $text .= $this->renderNode($child);
            }
            return $text;
        }

        if ($node instanceof HeadingNode) {
            $level = min(3, $node->level);
            $prefix = str_repeat('#', $level);
            $inner = $this->renderPlain($node);
            return "{$prefix} {$inner}\n\n";
        }

        if ($node instanceof ParagraphNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->renderNode($child);
            }
            return "{$inner}\n\n";
        }

        if ($node instanceof ListNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->renderNode($child);
            }
            return $inner . "\n";
        }

        if ($node instanceof ListItemNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->renderNode($child);
            }
            return "* {$inner}\n";
        }

        if ($node instanceof TextNode) {
            return $node->text;
        }

        if ($node instanceof StrongNode || $node instanceof EmphasisNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->renderNode($child);
            }
            return $inner;
        }

        if ($node instanceof CodeInlineNode) {
            return "`{$node->code}`";
        }

        if ($node instanceof WikilinkNode) {
            $this->links[] = [
                'target' => $node->target,
                'label' => $node->label
            ];
            return $node->label;
        }

        if ($node instanceof LinkNode) {
            $this->links[] = [
                'target' => $node->target,
                'label' => $node->label
            ];
            return $node->label;
        }

        if ($node instanceof ImageNode) {
            $alt = $node->label;
            $target = $node->target;

            // Resolve target path to the generated GIF
            $pathInfo = pathinfo($target);
            $gifName = $pathInfo['filename'] . '.gif';

            // Get absolute GIF path from slug
            $slug = $this->page ? trim($this->page->slug, '/') : '';
            if (str_ends_with($slug, '.html')) {
                $dir = dirname($slug);
                $gifPath = ($dir === '.' || $dir === '') ? '/' . $gifName : '/' . $dir . '/' . $gifName;
            } else {
                $gifPath = '/' . $slug . '/' . $gifName;
            }
            $gifPath = preg_replace('#/+#', '/', $gifPath);

            return "\n=> {$gifPath} [Foto: {$alt}]\n";
        }

        return '';
    }

    /**
     * Renders an AST node as plain text, stripping out any formatting.
     * Used for contexts where formatting is not supported (e.g., inside links).
     *
     * @param Node $node The AST node to render.
     * @return string The plain text representation.
     */
    private function renderPlain(Node $node): string
    {
        if ($node instanceof TextNode) {
            return $node->text;
        }
        if ($node instanceof CodeInlineNode) {
            return $node->code;
        }
        if ($node instanceof WikilinkNode) {
            return $node->label;
        }
        if ($node instanceof LinkNode) {
            return $node->label;
        }
        $text = '';
        foreach ($node->children as $child) {
            $text .= $this->renderPlain($child);
        }
        return $text;
    }
}
