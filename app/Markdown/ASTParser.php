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

            // 3. Paragraph Node
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
 * HTML Renderer (Visitor Pattern)
 * --------------------------------------------------------------------------
 */
class HtmlRenderer
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

        return '';
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
    }

    $output = $indentation . $className . $extra . "\n";
    foreach ($node->children as $child) {
        $output .= dumpAST($child, $indent + 1);
    }
    return $output;
}

/**
 * --------------------------------------------------------------------------
 * Test block (only runs when script is executed directly from CLI)
 * --------------------------------------------------------------------------
 */
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $testMarkdown = <<<MARKDOWN
# Obsidian Custom Parser Test

Este é um parágrafo de teste demonstrando **negrito** e *itálico*, além de `code inline` e links.
Temos também links normais e os famosos wikilinks do Obsidian, como [[Minha Nota]] simples ou [[Nota com Alias|Texto Customizado]].

- Primeiro item da lista com **negrito importante**
- Segundo item da lista contendo um link para [[Sobre Mim]]
- Terceiro item com _ênfase em itálico_
MARKDOWN;

    $parser = new ASTParser();
    $ast = $parser->parse($testMarkdown);

    $renderer = new HtmlRenderer();
    $html = $renderer->render($ast);

    echo "=== AST STRUCTURE ===\n";
    echo dumpAST($ast);
    echo "\n=== RENDERED HTML ===\n";
    echo $html;
}
