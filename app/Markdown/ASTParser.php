<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

/**
 * --------------------------------------------------------------------------
 * Abstract Syntax Tree (AST) Node Definitions
 * --------------------------------------------------------------------------
 */

/**
 * @property int $level
 * @property string $text
 * @property string $code
 * @property string $target
 * @property string $label
 */
abstract class Node
{
    /**
     * @var Node[]
     */
    public array $children = [];

    public ?string $rawText = null;
}

class RootNode extends Node
{
}

class HeadingNode extends Node
{
    public function __construct(
        public int $level
    ) {
    }
}

class ParagraphNode extends Node
{
}

class ListNode extends Node
{
}

class ListItemNode extends Node
{
}

abstract class InlineNode extends Node
{
}

class TextNode extends InlineNode
{
    public function __construct(
        public string $text
    ) {
    }
}

class StrongNode extends InlineNode
{
}

class EmphasisNode extends InlineNode
{
}

class CodeInlineNode extends InlineNode
{
    public function __construct(
        public string $code
    ) {
    }
}

class WikilinkNode extends InlineNode
{
    public function __construct(
        public string $target,
        public string $label
    ) {
    }
}

class LinkNode extends InlineNode
{
    public function __construct(
        public string $target,
        public string $label
    ) {
    }
}

class ImageNode extends InlineNode
{
    public function __construct(
        public string $target,
        public string $label
    ) {
    }
}

/**
 * Represents a block of raw HTML (e.g. <iframe>, <div>).
 */
class RawHtmlBlockNode extends Node
{
    public string $html = '';
}

/**
 * Represents an inline raw HTML tag (e.g. <br>, <span>).
 */
class RawHtmlInlineNode extends Node
{
    public string $html = '';
}

/**
 * --------------------------------------------------------------------------
 * AST Parser
 * --------------------------------------------------------------------------
 */
class ASTParser
{
    /**
     * Parse raw Markdown text into a RootNode AST.
     *
     * @param string $markdown
     * @return RootNode
     */
    public function parse(string $markdown): RootNode
    {
        $root = new RootNode();
        $lines = explode("\n", $markdown);

        /** @var Node|null $currentBlock */
        $currentBlock = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Empty line closes the active block context
            if ($trimmed === '') {
                $currentBlock = null;
                continue;
            }

            // 1. Heading Node
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $content = $matches[2];

                $heading = new HeadingNode($level);
                $heading->rawText = $content;
                $root->children[] = $heading;
                $currentBlock = null;
                continue;
            }

            // 2. List Item Node
            if (preg_match('/^(\s*)[-*]\s+(.+)$/', $line, $matches)) {
                $content = $matches[2];

                if (!($currentBlock instanceof ListNode)) {
                    $currentBlock = new ListNode();
                    $root->children[] = $currentBlock;
                }

                $item = new ListItemNode();
                $item->rawText = $content;
                $currentBlock->children[] = $item;
                continue;
            }

            // 3. HTML Block Node
            if ($currentBlock instanceof RawHtmlBlockNode) {
                $currentBlock->html .= "\n" . $line;
                continue;
            }
            
            if (preg_match('/^<([a-zA-Z0-9\-]+)(?:\s|>|\/>)/', $trimmed, $matches)) {
                $tag = strtolower($matches[1]);
                $inlineTags = ['a', 'span', 'b', 'strong', 'i', 'em', 'code', 'kbd', 'del', 'sub', 'sup'];
                if (!in_array($tag, $inlineTags)) {
                    $currentBlock = new RawHtmlBlockNode();
                    $currentBlock->html = $line;
                    $root->children[] = $currentBlock;
                    continue;
                }
            }

            // 4. Paragraph Node
            if ($currentBlock instanceof ParagraphNode) {
                $currentBlock->rawText .= "\n" . $line;
            } else {
                $currentBlock = new ParagraphNode();
                $currentBlock->rawText = $line;
                $root->children[] = $currentBlock;
            }
        }

        // Pass 2: Recursively parse raw block strings into inline AST nodes
        $this->parseInlinesRecursively($root);

        return $root;
    }

    /**
     * Walks the tree to replace rawText properties with their parsed inline child nodes.
     *
     * @param Node $node
     * @return void
     */
    private function parseInlinesRecursively(Node $node): void
    {
        if ($node->rawText !== null) {
            $node->children = $this->parseInlineText($node->rawText);
            $node->rawText = null;
        }

        foreach ($node->children as $child) {
            $this->parseInlinesRecursively($child);
        }
    }

    /**
     * A linear, single-pass scanner/lexer to tokenize and parse inline formatting.
     *
     * @param string $text
     * @return InlineNode[]
     */
    private function parseInlineText(string $text): array
    {
        /** @var InlineNode[] $nodes */
        $nodes = [];
        $len = strlen($text);
        $i = 0;
        $plainStart = 0;

        while ($i < $len) {
            // 0. Inline HTML tag: <tag> or </tag>
            if ($text[$i] === '<') {
                if (preg_match('/^<([\/a-zA-Z0-9\-]+\s*[^>]*)>/', substr($text, $i), $matches)) {
                    if ($i > $plainStart) {
                        $nodes[] = new TextNode(substr($text, $plainStart, $i - $plainStart));
                    }
                    $htmlNode = new RawHtmlInlineNode();
                    $htmlNode->html = $matches[0];
                    $nodes[] = $htmlNode;
                    
                    $i += strlen($matches[0]);
                    $plainStart = $i;
                    continue;
                }
            }

            // 1. Wikilinks: [[Target]] or [[Target|Alias]]
            if ($i + 1 < $len && $text[$i] === '[' && $text[$i + 1] === '[') {
                if ($i > $plainStart) {
                    $nodes[] = new TextNode(substr($text, $plainStart, $i - $plainStart));
                }

                $closePos = strpos($text, ']]', $i + 2);
                if ($closePos !== false) {
                    $inner = substr($text, $i + 2, $closePos - ($i + 2));
                    $parts = explode('|', $inner, 2);

                    if (count($parts) === 2) {
                        $target = trim($parts[0]);
                        $label = trim($parts[1]);
                    } else {
                        $target = trim($inner);
                        $label = $target;
                    }

                    $nodes[] = new WikilinkNode($target, $label);
                    $i = $closePos + 2;
                    $plainStart = $i;
                    continue;
                }
            }

            // 1.4. Images: ![Label](URL)
            if ($i + 1 < $len && $text[$i] === '!' && $text[$i + 1] === '[') {
                if ($i > $plainStart) {
                    $nodes[] = new TextNode(substr($text, $plainStart, $i - $plainStart));
                }

                $closeBracket = strpos($text, ']', $i + 2);
                if ($closeBracket !== false && $closeBracket + 1 < $len && $text[$closeBracket + 1] === '(') {
                    $closeParen = strpos($text, ')', $closeBracket + 2);
                    if ($closeParen !== false) {
                        $label = substr($text, $i + 2, $closeBracket - ($i + 2));
                        $target = substr($text, $closeBracket + 2, $closeParen - ($closeBracket + 2));

                        $nodes[] = new ImageNode($target, $label);
                        $i = $closeParen + 1;
                        $plainStart = $i;
                        continue;
                    }
                }
            }

            // 1.5. Standard Links: [Label](URL)
            if ($text[$i] === '[') {
                if ($i + 1 < $len && $text[$i + 1] !== '[') {
                    if ($i > $plainStart) {
                        $nodes[] = new TextNode(substr($text, $plainStart, $i - $plainStart));
                    }

                    $closeBracket = strpos($text, ']', $i + 1);
                    if ($closeBracket !== false && $closeBracket + 1 < $len && $text[$closeBracket + 1] === '(') {
                        $closeParen = strpos($text, ')', $closeBracket + 2);
                        if ($closeParen !== false) {
                            $label = substr($text, $i + 1, $closeBracket - ($i + 1));
                            $target = substr($text, $closeBracket + 2, $closeParen - ($closeBracket + 2));

                            $nodes[] = new LinkNode($target, $label);
                            $i = $closeParen + 1;
                            $plainStart = $i;
                            continue;
                        }
                    }
                }
            }

            // 2. Bold / Strong: **text**
            if ($i + 1 < $len && $text[$i] === '*' && $text[$i + 1] === '*') {
                if ($i > $plainStart) {
                    $nodes[] = new TextNode(substr($text, $plainStart, $i - $plainStart));
                }

                $closePos = strpos($text, '**', $i + 2);
                if ($closePos !== false) {
                    $inner = substr($text, $i + 2, $closePos - ($i + 2));
                    $strong = new StrongNode();
                    $strong->children = $this->parseInlineText($inner);
                    $nodes[] = $strong;

                    $i = $closePos + 2;
                    $plainStart = $i;
                    continue;
                }
            }

            // 3. Inline Code: `code`
            if ($text[$i] === '`') {
                if ($i > $plainStart) {
                    $nodes[] = new TextNode(substr($text, $plainStart, $i - $plainStart));
                }

                $closePos = strpos($text, '`', $i + 1);
                if ($closePos !== false) {
                    $code = substr($text, $i + 1, $closePos - ($i + 1));
                    $nodes[] = new CodeInlineNode($code);

                    $i = $closePos + 1;
                    $plainStart = $i;
                    continue;
                }
            }

            // 4. Emphasis / Italic: *text* or _text_
            if ($text[$i] === '*' || $text[$i] === '_') {
                $char = $text[$i];
                if ($i > $plainStart) {
                    $nodes[] = new TextNode(substr($text, $plainStart, $i - $plainStart));
                }

                $closePos = strpos($text, $char, $i + 1);
                if ($closePos !== false) {
                    $inner = substr($text, $i + 1, $closePos - ($i + 1));
                    $emphasis = new EmphasisNode();
                    $emphasis->children = $this->parseInlineText($inner);
                    $nodes[] = $emphasis;

                    $i = $closePos + 1;
                    $plainStart = $i;
                    continue;
                }
            }

            $i++;
        }

        if ($i > $plainStart) {
            $nodes[] = new TextNode(substr($text, $plainStart, $i - $plainStart));
        }

        return $nodes;
    }
}

/**
 * --------------------------------------------------------------------------
 * AST Utility Dumper
 * --------------------------------------------------------------------------
 */
function dumpAST(Node $node, int $indent = 0): string
{
    $indentation = str_repeat('  ', $indent);
    $className = (new \ReflectionClass($node))->getShortName();

    $extra = '';
    if ($node instanceof HeadingNode) {
        $extra = " (level: {$node->level})";
    } elseif ($node instanceof TextNode) {
        $extra = ": " . json_encode($node->text);
    } elseif ($node instanceof CodeInlineNode) {
        $extra = ": " . json_encode($node->code);
    } elseif ($node instanceof WikilinkNode) {
        $extra = " (target: " . json_encode($node->target) . ", label: " . json_encode($node->label) . ")";
    } elseif ($node instanceof LinkNode) {
        $extra = " (target: " . json_encode($node->target) . ", label: " . json_encode($node->label) . ")";
    } elseif ($node instanceof ImageNode) {
        $extra = " (target: " . json_encode($node->target) . ", label: " . json_encode($node->label) . ")";
    }

    $output = $indentation . $className . $extra . "\n";
    foreach ($node->children as $child) {
        $output .= dumpAST($child, $indent + 1);
    }
    return $output;
}
