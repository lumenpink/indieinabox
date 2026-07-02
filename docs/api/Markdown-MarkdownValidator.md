# MarkdownValidator
**Namespace:** `Indieinabox\Markdown`

--------------------------------------------------------------------------
Markdown integrity validator (Linter/Sanitizer)
--------------------------------------------------------------------------

## Properties

### `private string $markdown`

@var string

## Methods

### __construct()
`public function __construct(string $markdown)`

Method __construct
@param string $markdown

### validate()
`public function validate(): Indieinabox\Markdown\ValidationResult`

Runs full diagnostics on the Markdown content.

@return ValidationResult

### validateParagraphInlines()
`private function validateParagraphInlines(string $text, int $startLine, array $errors, array $warnings): void`

Performs character-by-character validation of inline formatting markers inside a paragraph block.

@param string $text
@param int $startLine
@param ValidationError[] &$errors
@param ValidationWarning[] &$warnings
@return void
