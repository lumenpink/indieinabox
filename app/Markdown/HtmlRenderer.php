<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

class HtmlRenderer implements RendererInterface
{
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
            $targetEsc = htmlspecialchars($node->target, ENT_QUOTES | ENT_HTML5);
            $labelEsc = htmlspecialchars($node->label, ENT_QUOTES | ENT_HTML5);
            return "<a href=\"{$targetEsc}\">{$labelEsc}</a>";
        }

        if ($node instanceof LinkNode) {
            $targetEsc = htmlspecialchars($node->target, ENT_QUOTES | ENT_HTML5);
            $labelEsc = htmlspecialchars($node->label, ENT_QUOTES | ENT_HTML5);
            return "<a href=\"{$targetEsc}\">{$labelEsc}</a>";
        }

        return '';
    }
}
