# ASTParser
**Namespace:** `Indieinabox\Markdown`

--------------------------------------------------------------------------
AST Parser
--------------------------------------------------------------------------

## Methods

### parse()
`public function parse(string $markdown): Indieinabox\Markdown\RootNode`

Parse raw Markdown text into a RootNode AST.

@param string $markdown
@return RootNode

### parseInlinesRecursively()
`private function parseInlinesRecursively(Indieinabox\Markdown\Node $node): void`

Walks the tree to replace rawText properties with their parsed inline child nodes.

@param Node $node
@return void

### parseInlineText()
`private function parseInlineText(string $text): array`

A linear, single-pass scanner/lexer to tokenize and parse inline formatting.

@param string $text
@return InlineNode[]
