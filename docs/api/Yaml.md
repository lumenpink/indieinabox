# Yaml
**Namespace:** `Indieinabox`

Yaml Class

A Simple & Lightweight PHP/YAML Class
This is the maintained version of spyc library, it was renamed to Yaml

This class can be used to read a YAML file and convert its contents
into a PHP array.  It currently supports a very limited subsection of
the YAML spec.

-----------------------------------------------------------------------------
This Class is mantained version of spyc lib
Code subject to the MIT license
Copyright (c) 2011 Vladimir Andersen
-----------------------------------------------------------------------------

Usage:
<code>
  $yaml  = new Yaml();
  $array = $yaml->load($file);
</code>

@author    Vlad Andersen <vlad.andersen@gmail.com>
@author    Chris Wanstrath <chris@ozmm.org>
@author    Erik Amaru Ortiz <aortiz.erik@gmail.com>
@link      (origin) http://code.google.com/p/spyc/ last rev. 2011
@link      (current) https://github.com/eriknyk/Yaml
@copyright Copyright 2005-2006 Chris Wanstrath,
@copyright 2006-2011 Vlad Andersen
@copyright 2012 Erik Amaru Ortiz <aortiz.erik@gmail.com>
@license   http://www.opensource.org/licenses/mit-license.php MIT License
@package   Yaml
@version   1.0

## Properties

### `public bool $settingDumpForceQuotes`

Setting this to true will force YAMLDump to enclose any string value in
quotes.  False by default.

@var bool

### `private int $dumpIndent`

Private vars.

@var mixed

### `private int $dumpWordWrap`

@var int

### `private mixed $containsGroupAnchor`

@var mixed

### `private mixed $containsGroupAlias`

@var mixed

### `private array $path`

@var array

### `private array $result`

@var array

### `private string $LiteralPlaceHolder`

@var string

### `private array $SavedGroups`

@var array

### `private int $indent`

@var int

### `private array $delayedPath`

Path modifier that should be applied after adding current element.

@var array

### `public mixed $nodeId`

#@+

@access public
@var    mixed

## Methods

### __construct()
`public function __construct(string $file = '')`

Yaml Construct

@param string $file (alternative) path of yaml file

### load()
`public function load(string $file): array`

Load a yaml file & parse

@param  string $file path of yaml file
@return array        yaml parsed result

### loadString()
`public function loadString(string $yamlContent): array`

Load a yaml string & parse

@param  string $yamlContent string conatining yaml content
@return array               yaml parsed result

### loadFile()
`public function loadFile(string $file): array`

Load a valid YAML file to Spyc.

@param  string $file
@return array

### removeComments()
`public function removeComments(array $array): array`

Strip comments from YAML

@param  array $array
@return array

### dump()
`public function dump(mixed $array, mixed $indent = false, mixed $wordwrap = false): string`

Dump PHP array to YAML

The dump method, when supplied with an array, will do its best
to convert the array into friendly YAML.  Pretty simple.  Feel free to
save the returned string as tasteful.yaml and pass it around.

Oh, and you can decide how big the indent is and what the wordwrap
for folding is.  Pretty cool -- just pass in 'false' for either if
you want to use the default.

Indent's default is 2 spaces, wordwrap's default is 40 characters.  And
you can turn off wordwrap by passing in 0.

@access public
@return string
@param  array $array    PHP array
@param  int   $indent   Pass in false to use the default, which is 2
@param  int   $wordwrap Pass in 0 for no wordwrap, false for default (40)

### yamlize()
`private function yamlize(mixed $key, mixed $value, int $indent, mixed $previous_key = -1, mixed $first_key = 0, ?array $source_array = null): string`

Attempts to convert a key / value array item to YAML

@access private
@return string
@param  string|int $key    The name of the key
@param  mixed      $value  The value of the item
@param  int        $indent The indent of the current node

### yamlizeArray()
`private function yamlizeArray(array $array, int $indent): string`

Attempts to convert an array to YAML

@access private
@return string
@param  $array  The array you want to convert
@param  $indent The indent of the current level

### dumpNode()
`private function dumpNode(mixed $key, mixed $value, int $indent, mixed $previous_key = -1, mixed $first_key = 0, ?array $source_array = null): string`

Returns YAML from a key and a value

@access private
@return string
@param  string|int $key    The name of the key
@param  mixed      $value  The value of the item
@param  int        $indent The indent of the current node

### doLiteralBlock()
`private function doLiteralBlock(string $value, int $indent): string`

Creates a literal block for dumping

@access private
@return string
@param  $value
@param  $indent int The value of the indent

### doFolding()
`private function doFolding(mixed $value, int $indent)`

Folds a string of text, if necessary

@access private
@param  mixed $value The string you wish to fold
@param  int   $indent
@return mixed

### loadWithSource()
`private function loadWithSource(array $Source): array`

Method loadWithSource
@param array $Source

@return array

### loadFromFile()
`private function loadFromFile(string $file): array`

Method loadFromFile
@param string $file

@return array

### loadFromString()
`private function loadFromString(string $input): array`

Method loadFromString
@param string $input

@return array

### parseLine()
`private function parseLine(string $line): array`

Parses YAML code and returns an array for a node

@access private
@return array
@param  string $line A line from the YAML file

### toType()
`private function toType(mixed $value)`

Finds the type of the passed value, returns the value as the new type.

@access private
@param  string $value
@return mixed

### inlineEscape()
`private function inlineEscape(string $inline): array`

Used in inlines to check for more inlines or quoted strings

@access private
@return array

### literalBlockContinues()
`private function literalBlockContinues(string $line, int $lineIndent): bool`

Method literalBlockContinues
@param string $line
@param int $lineIndent

@return bool

### referenceContentsByAlias()
`private function referenceContentsByAlias(string $alias)`

Method referenceContentsByAlias
@param string $alias

### addArrayInline()
`private function addArrayInline(array $array, int $indent): bool`

Method addArrayInline
@param array $array
@param int $indent

@return bool

### addArray()
`private function addArray(array $incoming_data, int $incoming_indent): void`

Method addArray
@param array $incoming_data
@param int $incoming_indent

@return void

### startsLiteralBlock()
`private static function startsLiteralBlock(string $line)`

Method startsLiteralBlock
@param string $line

### greedilyNeedNextLine()
`private static function greedilyNeedNextLine(string $line): bool`

Method greedilyNeedNextLine
@param string $line

@return bool

### addLiteralLine()
`private function addLiteralLine(string $literalBlock, string $line, string $literalBlockStyle, int $indent = -1): string`

Method addLiteralLine
@param string $literalBlock
@param string $line
@param string $literalBlockStyle
@param int $indent

@return string

### revertLiteralPlaceHolder()
`public function revertLiteralPlaceHolder(array $lineArray, string $literalBlock): array`

Method revertLiteralPlaceHolder
@param array $lineArray
@param string $literalBlock

@return array

### stripIndent()
`private static function stripIndent(string $line, int $indent = -1): string`

Method stripIndent
@param string $line
@param int $indent

@return string

### getParentPathByIndent()
`private function getParentPathByIndent(int $indent): array`

Method getParentPathByIndent
@param int $indent

@return array

### clearBiggerPathValues()
`private function clearBiggerPathValues(int $indent): bool`

Method clearBiggerPathValues
@param int $indent

@return bool

### isComment()
`private static function isComment(string $line): bool`

Method isComment
@param string $line

@return bool

### isEmpty()
`private static function isEmpty(string $line): bool`

Method isEmpty
@param string $line

@return bool

### isArrayElement()
`private function isArrayElement(string $line): bool`

Method isArrayElement
@param string $line

@return bool

### isHashElement()
`private function isHashElement(string $line)`

Method isHashElement
@param string $line

### isLiteral()
`private function isLiteral(string $line): bool`

Method isLiteral
@param string $line

@return bool

### unquote()
`private static function unquote(mixed $value)`

@param  mixed $value
@return mixed

### startsMappedSequence()
`private function startsMappedSequence(string $line): bool`

Method startsMappedSequence
@param string $line

@return bool

### returnMappedSequence()
`private function returnMappedSequence(string $line): array`

Method returnMappedSequence
@param string $line

@return array

### returnMappedValue()
`private function returnMappedValue(string $line): array`

Method returnMappedValue
@param string $line

@return array

### startsMappedValue()
`private function startsMappedValue(string $line): bool`

Method startsMappedValue
@param string $line

@return bool

### isPlainArray()
`private function isPlainArray(string $line): bool`

Method isPlainArray
@param string $line

@return bool

### returnPlainArray()
`private function returnPlainArray(string $line)`

Method returnPlainArray
@param string $line

### returnKeyValuePair()
`private function returnKeyValuePair(string $line): array`

Method returnKeyValuePair
@param string $line

@return array

### returnArrayElement()
`private function returnArrayElement(string $line): array`

Method returnArrayElement
@param string $line

@return array

### nodeContainsGroup()
`private function nodeContainsGroup(string $line)`

Method nodeContainsGroup
@param string $line

### addGroup()
`private function addGroup(string $line, string $group): void`

Method addGroup
@param string $line
@param string $group

@return void

### stripGroup()
`private function stripGroup(string $line, string $group): string`

Method stripGroup
@param string $line
@param string $group

@return string
