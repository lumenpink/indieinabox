<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

/**
 * Class GophermapRenderer
 */
class GophermapRenderer implements RendererInterface
{
    /**
     * @var array<array{type: string, label: string, target: string}>
     */
    private array $links = [];
    /**
     * @var string
     */
    private string $host;
    /**
     * @var int
     */
    private int $port;

    /**
     * @var \Indieinabox\Page|null
     */
    private ?\Indieinabox\Page $page = null;

    /**
     * Initializes the GophermapRenderer.
     *
     * @param string $host The hostname to use for internal Gopher links.
     * @param int|string $port The port number to use for internal Gopher links.
     * @param \Indieinabox\Page|null $page The page being rendered, used for resolving relative links.
     */
    public function __construct(string $host = 'gopher.example.com', int $port = 70, ?\Indieinabox\Page $page = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->page = $page;
    }

    /**
     * Renders a Node AST to Gophermap format.
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
            return $body . "\r\n";
        }

        // Append collected links at the end of the document
        $linksSection = "\r\n" . $this->formatLine('i', '');
        foreach ($this->links as $link) {
            $linksSection .= $this->formatLine($link['type'], $link['label'], $link['target'], $this->host, $this->port);
        }
        return $body . $linksSection;
    }

    /**
     * Formats a line of text into a valid Gophermap entry.
     *
     * @param string $type The Gopher item type character (e.g., 'i' for info, '1' for directory, 'h' for HTML).
     * @param string $display The text to display to the user.
     * @param string $selector The path or selector for the resource.
     * @param string|null $host The target hostname (defaults to this renderer's host if null).
     * @param int|string|null $port The target port (defaults to this renderer's port if null).
     * @return string The formatted Gophermap line, terminated with CRLF.
     */
    private function formatLine(string $type, string $display, string $selector = '', string $host = '(null)', int $port = 0): string
    {
        return "{$type}{$display}\t{$selector}\t{$host}\t{$port}\r\n";
    }

    /**
     * Recursively renders an AST node into Gophermap format.
     * Handles specific node types like headings, lists, quotes, and links,
     * ensuring proper wrapping and RFC 1436 formatting.
     *
     * @param \Indieinabox\Markdown\Node $node The AST node to render.
     * @return string The rendered Gophermap string.
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
            $inner = $this->renderPlain($node);
            $heading = "=== {$inner} ===";
            return $this->formatLine('i', $heading) . $this->formatLine('i', '');
        }

        if ($node instanceof ParagraphNode) {
            $text = '';
            $currentTextGroup = '';
            
            foreach ($node->children as $child) {
                if ($child instanceof ImageNode) {
                    if ($currentTextGroup !== '') {
                        $wrapped = wordwrap($currentTextGroup, 70, "\n");
                        foreach (explode("\n", $wrapped) as $line) {
                            $text .= $this->formatLine('i', $line);
                        }
                        $currentTextGroup = '';
                    }
                    $text .= $this->renderNode($child);
                } else {
                    $currentTextGroup .= $this->renderNode($child);
                }
            }
            
            if ($currentTextGroup !== '') {
                $wrapped = wordwrap($currentTextGroup, 70, "\n");
                foreach (explode("\n", $wrapped) as $line) {
                    $text .= $this->formatLine('i', $line);
                }
            }
            
            return $text . $this->formatLine('i', '');
        }

        if ($node instanceof ListNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->renderNode($child);
            }
            return $inner . $this->formatLine('i', '');
        }

        if ($node instanceof ListItemNode) {
            $inner = '';
            foreach ($node->children as $child) {
                $inner .= $this->renderNode($child);
            }
            return $this->formatLine('i', "* {$inner}");
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
            $target = $node->target;
            if (substr($target, 0, 1) !== '/') {
                $target = '/' . $target;
            }
            $this->links[] = [
                'type' => '0',
                'label' => $node->label,
                'target' => $target
            ];
            return $node->label;
        }

        if ($node instanceof LinkNode) {
            $target = $node->target;
            $type = '0';

            if (preg_match('/^https?:\/\//i', $target)) {
                $type = 'h';
                $target = "URL:{$target}";
            } else {
                if (substr($target, 0, 1) !== '/') {
                    $target = '/' . $target;
                }
            }

            $this->links[] = [
                'type' => $type,
                'label' => $node->label,
                'target' => $target
            ];
            return $node->label;
        }

        if ($node instanceof ImageNode) {
            $alt = $node->label;
            $target = $node->target;

            // Resolve selector path to the generated GIF
            $pathInfo = pathinfo($target);
            $gifName = $pathInfo['filename'] . '.gif';

            $slug = $this->page ? trim($this->page->slug, '/') : '';
            if (str_ends_with($slug, '.html')) {
                $dir = dirname($slug);
                $gifPath = ($dir === '.' || $dir === '') ? '/' . $gifName : '/' . $dir . '/' . $gifName;
            } else {
                $gifPath = '/' . $slug . '/' . $gifName;
            }
            $gifPath = preg_replace('#/+#', '/', $gifPath);

            return $this->formatLine('I', "[Foto: {$alt}]", $gifPath, $this->host, $this->port);
        }

        return '';
    }

    /**
     * Renders an AST node as plain text, stripping out any formatting.
     * Used for contexts where formatting is not supported (e.g., inside link labels).
     *
     * @param \Indieinabox\Markdown\Node $node The AST node to render.
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
