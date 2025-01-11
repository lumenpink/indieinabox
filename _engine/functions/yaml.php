<?php

namespace Symfony\Component\Yaml {

    use Symfony\Component\Yaml\Exception\ParseException;

    /**
     * Yaml offers convenience methods to load and dump YAML.
     *
     * @author Fabien Potencier <fabien@symfony.com>
     *
     * @final
     */
    class Yaml
    {
        public const DUMP_OBJECT = 1;
        public const PARSE_EXCEPTION_ON_INVALID_TYPE = 2;
        public const PARSE_OBJECT = 4;
        public const PARSE_OBJECT_FOR_MAP = 8;
        public const DUMP_EXCEPTION_ON_INVALID_TYPE = 16;
        public const PARSE_DATETIME = 32;
        public const DUMP_OBJECT_AS_MAP = 64;
        public const DUMP_MULTI_LINE_LITERAL_BLOCK = 128;
        public const PARSE_CONSTANT = 256;
        public const PARSE_CUSTOM_TAGS = 512;
        public const DUMP_EMPTY_ARRAY_AS_SEQUENCE = 1024;
        public const DUMP_NULL_AS_TILDE = 2048;
        public const DUMP_NUMERIC_KEY_AS_STRING = 4096;
        /**
         * Parses a YAML file into a PHP value.
         *
         * Usage:
         *
         *     $array = Yaml::parseFile('config.yml');
         *     print_r($array);
         *
         * @param string                     $filename The path to the YAML file to be parsed
         * @param int-mask-of<self::PARSE_*> $flags    A bit field of PARSE_* constants to customize the YAML parser behavior
         *
         * @throws ParseException If the file could not be read or the YAML is not valid
         */
        public static function parseFile(string $filename, int $flags = 0): mixed
        {
            $yaml = new Parser();
            return $yaml->parseFile($filename, $flags);
        }
        /**
         * Parses YAML into a PHP value.
         *
         *  Usage:
         *  <code>
         *   $array = Yaml::parse(file_get_contents('config.yml'));
         *   print_r($array);
         *  </code>
         *
         * @param string                     $input A string containing YAML
         * @param int-mask-of<self::PARSE_*> $flags A bit field of PARSE_* constants to customize the YAML parser behavior
         *
         * @throws ParseException If the YAML is not valid
         */
        public static function parse(string $input, int $flags = 0): mixed
        {
            $yaml = new Parser();
            return $yaml->parse($input, $flags);
        }
        /**
         * Dumps a PHP value to a YAML string.
         *
         * The dump method, when supplied with an array, will do its best
         * to convert the array into friendly YAML.
         *
         * @param mixed                     $input  The PHP value
         * @param int                       $inline The level where you switch to inline YAML
         * @param int                       $indent The amount of spaces to use for indentation of nested nodes
         * @param int-mask-of<self::DUMP_*> $flags  A bit field of DUMP_* constants to customize the dumped YAML string
         */
        public static function dump(mixed $input, int $inline = 2, int $indent = 4, int $flags = 0): string
        {
            $yaml = new Dumper($indent);
            return $yaml->dump($input, $inline, 0, $flags);
        }
    }
}

namespace Symfony\Component\Yaml {

    use Symfony\Component\Yaml\Exception\ParseException;
    use Symfony\Component\Yaml\Tag\TaggedValue;

    /**
     * Parser parses YAML strings to convert them to PHP arrays.
     *
     * @author Fabien Potencier <fabien@symfony.com>
     *
     * @final
     */
    class Parser
    {
        public const TAG_PATTERN = '(?P<tag>![\\w!.\\/:-]+)';
        public const BLOCK_SCALAR_HEADER_PATTERN = '(?P<separator>\\||>)(?P<modifiers>\\+|\\-|\\d+|\\+\\d+|\\-\\d+|\\d+\\+|\\d+\\-)?(?P<comments> +#.*)?';
        public const REFERENCE_PATTERN = '#^&(?P<ref>[^ ]++) *+(?P<value>.*)#u';
        private ?string $filename = null;
        private int $offset = 0;
        private int $numberOfParsedLines = 0;
        private ?int $totalNumberOfLines = null;
        private array $lines = [];
        private int $currentLineNb = -1;
        private string $currentLine = '';
        private array $refs = [];
        private array $skippedLineNumbers = [];
        private array $locallySkippedLineNumbers = [];
        private array $refsBeingParsed = [];
        /**
         * Parses a YAML file into a PHP value.
         *
         * @param string                     $filename The path to the YAML file to be parsed
         * @param int-mask-of<Yaml::PARSE_*> $flags    A bit field of Yaml::PARSE_* constants to customize the YAML parser behavior
         *
         * @throws ParseException If the file could not be read or the YAML is not valid
         */
        public function parseFile(string $filename, int $flags = 0): mixed
        {
            if (!is_file($filename)) {
                throw new ParseException(\sprintf('File "%s" does not exist.', $filename));
            }
            if (!is_readable($filename)) {
                throw new ParseException(\sprintf('File "%s" cannot be read.', $filename));
            }
            $this->filename = $filename;
            try {
                return $this->parse(file_get_contents($filename), $flags);
            } finally {
                $this->filename = null;
            }
        }
        /**
         * Parses a YAML string to a PHP value.
         *
         * @param string                     $value A YAML string
         * @param int-mask-of<Yaml::PARSE_*> $flags A bit field of Yaml::PARSE_* constants to customize the YAML parser behavior
         *
         * @throws ParseException If the YAML is not valid
         */
        public function parse(string $value, int $flags = 0): mixed
        {
            if (false === preg_match('//u', $value)) {
                throw new ParseException('The YAML value does not appear to be valid UTF-8.', -1, null, $this->filename);
            }
            $this->refs = [];
            try {
                $data = $this->doParse($value, $flags);
            } finally {
                $this->refsBeingParsed = [];
                $this->offset = 0;
                $this->lines = [];
                $this->currentLine = '';
                $this->numberOfParsedLines = 0;
                $this->refs = [];
                $this->skippedLineNumbers = [];
                $this->locallySkippedLineNumbers = [];
                $this->totalNumberOfLines = null;
            }
            return $data;
        }
        private function doParse(string $value, int $flags): mixed
        {
            $this->currentLineNb = -1;
            $this->currentLine = '';
            $value = $this->cleanup($value);
            $this->lines = explode("\n", $value);
            $this->numberOfParsedLines = \count($this->lines);
            $this->locallySkippedLineNumbers = [];
            $this->totalNumberOfLines ??= $this->numberOfParsedLines;
            if (!$this->moveToNextLine()) {
                return null;
            }
            $data = [];
            $context = null;
            $allowOverwrite = false;
            while ($this->isCurrentLineEmpty()) {
                if (!$this->moveToNextLine()) {
                    return null;
                }
            }
            // Resolves the tag and returns if end of the document
            if (null !== ($tag = $this->getLineTag($this->currentLine, $flags, false)) && !$this->moveToNextLine()) {
                return new TaggedValue($tag, '');
            }
            do {
                if ($this->isCurrentLineEmpty()) {
                    continue;
                }
                // tab?
                if ("\t" === $this->currentLine[0]) {
                    throw new ParseException('A YAML file cannot contain tabs as indentation.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                }
                Inline::initialize($flags, $this->getRealCurrentLineNb(), $this->filename);
                $isRef = $mergeNode = false;
                if ('-' === $this->currentLine[0] && self::preg_match('#^\\-((?P<leadspaces>\\s+)(?P<value>.+))?$#u', rtrim($this->currentLine), $values)) {
                    if ($context && 'mapping' == $context) {
                        throw new ParseException('You cannot define a sequence item when in a mapping.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }
                    $context = 'sequence';
                    if (isset($values['value']) && '&' === $values['value'][0] && self::preg_match(self::REFERENCE_PATTERN, $values['value'], $matches)) {
                        $isRef = $matches['ref'];
                        $this->refsBeingParsed[] = $isRef;
                        $values['value'] = $matches['value'];
                    }
                    if (isset($values['value'][1]) && '?' === $values['value'][0] && ' ' === $values['value'][1]) {
                        throw new ParseException('Complex mappings are not supported.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
                    }
                    // array
                    if (isset($values['value']) && str_starts_with(ltrim($values['value'], ' '), '-')) {
                        // Inline first child
                        $currentLineNumber = $this->getRealCurrentLineNb();
                        $sequenceIndentation = \strlen($values['leadspaces']) + 1;
                        $sequenceYaml = substr($this->currentLine, $sequenceIndentation);
                        $sequenceYaml .= "\n" . $this->getNextEmbedBlock($sequenceIndentation, true);
                        $data[] = $this->parseBlock($currentLineNumber, rtrim($sequenceYaml), $flags);
                    } elseif (!isset($values['value']) || '' == trim($values['value'], ' ') || str_starts_with(ltrim($values['value'], ' '), '#')) {
                        $data[] = $this->parseBlock($this->getRealCurrentLineNb() + 1, $this->getNextEmbedBlock(null, true) ?? '', $flags);
                    } elseif (null !== ($subTag = $this->getLineTag(ltrim($values['value'], ' '), $flags))) {
                        $data[] = new TaggedValue($subTag, $this->parseBlock($this->getRealCurrentLineNb() + 1, $this->getNextEmbedBlock(null, true), $flags));
                    } else {
                        if (isset($values['leadspaces']) && ('!' === $values['value'][0] || self::preg_match('#^(?P<key>' . Inline::REGEX_QUOTED_STRING . '|[^ \'"\\{\\[].*?) *\\:(\\s+(?P<value>.+?))?\\s*$#u', $this->trimTag($values['value']), $matches))) {
                            $block = $values['value'];
                            if ($this->isNextLineIndented() || isset($matches['value']) && '>-' === $matches['value']) {
                                $block .= "\n" . $this->getNextEmbedBlock($this->getCurrentLineIndentation() + \strlen($values['leadspaces']) + 1);
                            }
                            $data[] = $this->parseBlock($this->getRealCurrentLineNb(), $block, $flags);
                        } else {
                            $data[] = $this->parseValue($values['value'], $flags, $context);
                        }
                    }
                    if ($isRef) {
                        $this->refs[$isRef] = end($data);
                        array_pop($this->refsBeingParsed);
                    }
                } elseif (self::preg_match('#^(?P<key>(?:![^\\s]++\\s++)?(?:' . Inline::REGEX_QUOTED_STRING . '|[^ \'"\\[\\{!].*?)) *\\:(( |\\t)++(?P<value>.+))?$#u', rtrim($this->currentLine), $values) && (!str_contains($values['key'], ' #') || \in_array($values['key'][0], ['"', "'"]))) {
                    if ($context && 'sequence' == $context) {
                        throw new ParseException('You cannot define a mapping item when in a sequence.', $this->currentLineNb + 1, $this->currentLine, $this->filename);
                    }
                    $context = 'mapping';
                    try {
                        $key = Inline::parseScalar($values['key']);
                    } catch (ParseException $e) {
                        $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                        $e->setSnippet($this->currentLine);
                        throw $e;
                    }
                    if (!\is_string($key) && !\is_int($key)) {
                        throw new ParseException((is_numeric($key) ? 'Numeric' : 'Non-string') . ' keys are not supported. Quote your evaluable mapping keys instead.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
                    }
                    // Convert float keys to strings, to avoid being converted to integers by PHP
                    if (\is_float($key)) {
                        $key = (string) $key;
                    }
                    if ('<<' === $key && (!isset($values['value']) || '&' !== $values['value'][0] || !self::preg_match('#^&(?P<ref>[^ ]+)#u', $values['value'], $refMatches))) {
                        $mergeNode = true;
                        $allowOverwrite = true;
                        if (isset($values['value'][0]) && '*' === $values['value'][0]) {
                            $refName = substr(rtrim($values['value']), 1);
                            if (!\array_key_exists($refName, $this->refs)) {
                                if (false !== ($pos = array_search($refName, $this->refsBeingParsed, true))) {
                                    throw new ParseException(\sprintf('Circular reference [%s] detected for reference "%s".', implode(', ', array_merge(\array_slice($this->refsBeingParsed, $pos), [$refName])), $refName), $this->currentLineNb + 1, $this->currentLine, $this->filename);
                                }
                                throw new ParseException(\sprintf('Reference "%s" does not exist.', $refName), $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }
                            $refValue = $this->refs[$refName];
                            if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && $refValue instanceof \stdClass) {
                                $refValue = (array) $refValue;
                            }
                            if (!\is_array($refValue)) {
                                throw new ParseException('YAML merge keys used with a scalar value instead of an array.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }
                            $data += $refValue;
                            // array union
                        } else {
                            if (isset($values['value']) && '' !== $values['value']) {
                                $value = $values['value'];
                            } else {
                                $value = $this->getNextEmbedBlock();
                            }
                            $parsed = $this->parseBlock($this->getRealCurrentLineNb() + 1, $value, $flags);
                            if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && $parsed instanceof \stdClass) {
                                $parsed = (array) $parsed;
                            }
                            if (!\is_array($parsed)) {
                                throw new ParseException('YAML merge keys used with a scalar value instead of an array.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }
                            if (isset($parsed[0])) {
                                // If the value associated with the merge key is a sequence, then this sequence is expected to contain mapping nodes
                                // and each of these nodes is merged in turn according to its order in the sequence. Keys in mapping nodes earlier
                                // in the sequence override keys specified in later mapping nodes.
                                foreach ($parsed as $parsedItem) {
                                    if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && $parsedItem instanceof \stdClass) {
                                        $parsedItem = (array) $parsedItem;
                                    }
                                    if (!\is_array($parsedItem)) {
                                        throw new ParseException('Merge items must be arrays.', $this->getRealCurrentLineNb() + 1, $parsedItem, $this->filename);
                                    }
                                    $data += $parsedItem;
                                    // array union
                                }
                            } else {
                                // If the value associated with the key is a single mapping node, each of its key/value pairs is inserted into the
                                // current mapping, unless the key already exists in it.
                                $data += $parsed;
                                // array union
                            }
                        }
                    } elseif ('<<' !== $key && isset($values['value']) && '&' === $values['value'][0] && self::preg_match(self::REFERENCE_PATTERN, $values['value'], $matches)) {
                        $isRef = $matches['ref'];
                        $this->refsBeingParsed[] = $isRef;
                        $values['value'] = $matches['value'];
                    }
                    $subTag = null;
                    if ($mergeNode) {
                        // Merge keys
                    } elseif (!isset($values['value']) || '' === $values['value'] || str_starts_with($values['value'], '#') || null !== ($subTag = $this->getLineTag($values['value'], $flags)) || '<<' === $key) {
                        // hash
                        // if next line is less indented or equal, then it means that the current value is null
                        if (!$this->isNextLineIndented() && !$this->isNextLineUnIndentedCollection()) {
                            // Spec: Keys MUST be unique; first one wins.
                            // But overwriting is allowed when a merge node is used in current block.
                            if ($allowOverwrite || !isset($data[$key])) {
                                if (!$allowOverwrite && \array_key_exists($key, $data)) {
                                    trigger_deprecation('symfony/yaml', '7.2', 'Duplicate key "%s" detected on line %d whilst parsing YAML. Silent handling of duplicate mapping keys in YAML is deprecated and will throw a ParseException in 8.0.', $key, $this->getRealCurrentLineNb() + 1);
                                }
                                if (null !== $subTag) {
                                    $data[$key] = new TaggedValue($subTag, '');
                                } else {
                                    $data[$key] = null;
                                }
                            } else {
                                throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), $this->getRealCurrentLineNb() + 1, $this->currentLine);
                            }
                        } else {
                            // remember the parsed line number here in case we need it to provide some contexts in error messages below
                            $realCurrentLineNbKey = $this->getRealCurrentLineNb();
                            $value = $this->parseBlock($this->getRealCurrentLineNb() + 1, $this->getNextEmbedBlock(), $flags);
                            if ('<<' === $key) {
                                $this->refs[$refMatches['ref']] = $value;
                                if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && $value instanceof \stdClass) {
                                    $value = (array) $value;
                                }
                                $data += $value;
                            } elseif ($allowOverwrite || !isset($data[$key])) {
                                if (!$allowOverwrite && \array_key_exists($key, $data)) {
                                    trigger_deprecation('symfony/yaml', '7.2', 'Duplicate key "%s" detected on line %d whilst parsing YAML. Silent handling of duplicate mapping keys in YAML is deprecated and will throw a ParseException in 8.0.', $key, $this->getRealCurrentLineNb() + 1);
                                }
                                // Spec: Keys MUST be unique; first one wins.
                                // But overwriting is allowed when a merge node is used in current block.
                                if (null !== $subTag) {
                                    $data[$key] = new TaggedValue($subTag, $value);
                                } else {
                                    $data[$key] = $value;
                                }
                            } else {
                                throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), $realCurrentLineNbKey + 1, $this->currentLine);
                            }
                        }
                    } else {
                        $value = $this->parseValue(rtrim($values['value']), $flags, $context);
                        // Spec: Keys MUST be unique; first one wins.
                        // But overwriting is allowed when a merge node is used in current block.
                        if ($allowOverwrite || !isset($data[$key])) {
                            if (!$allowOverwrite && \array_key_exists($key, $data)) {
                                trigger_deprecation('symfony/yaml', '7.2', 'Duplicate key "%s" detected on line %d whilst parsing YAML. Silent handling of duplicate mapping keys in YAML is deprecated and will throw a ParseException in 8.0.', $key, $this->getRealCurrentLineNb() + 1);
                            }
                            $data[$key] = $value;
                        } else {
                            throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), $this->getRealCurrentLineNb() + 1, $this->currentLine);
                        }
                    }
                    if ($isRef) {
                        $this->refs[$isRef] = $data[$key];
                        array_pop($this->refsBeingParsed);
                    }
                } elseif ('"' === $this->currentLine[0] || "'" === $this->currentLine[0]) {
                    if (null !== $context) {
                        throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }
                    try {
                        return Inline::parse($this->lexInlineQuotedString(), $flags, $this->refs);
                    } catch (ParseException $e) {
                        $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                        $e->setSnippet($this->currentLine);
                        throw $e;
                    }
                } elseif ('{' === $this->currentLine[0]) {
                    if (null !== $context) {
                        throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }
                    try {
                        $parsedMapping = Inline::parse($this->lexInlineMapping(), $flags, $this->refs);
                        while ($this->moveToNextLine()) {
                            if (!$this->isCurrentLineEmpty()) {
                                throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }
                        }
                        return $parsedMapping;
                    } catch (ParseException $e) {
                        $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                        $e->setSnippet($this->currentLine);
                        throw $e;
                    }
                } elseif ('[' === $this->currentLine[0]) {
                    if (null !== $context) {
                        throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }
                    try {
                        $parsedSequence = Inline::parse($this->lexInlineSequence(), $flags, $this->refs);
                        while ($this->moveToNextLine()) {
                            if (!$this->isCurrentLineEmpty()) {
                                throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }
                        }
                        return $parsedSequence;
                    } catch (ParseException $e) {
                        $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                        $e->setSnippet($this->currentLine);
                        throw $e;
                    }
                } else {
                    // multiple documents are not supported
                    if ('---' === $this->currentLine) {
                        throw new ParseException('Multiple documents are not supported.', $this->currentLineNb + 1, $this->currentLine, $this->filename);
                    }
                    if (isset($this->currentLine[1]) && '?' === $this->currentLine[0] && ' ' === $this->currentLine[1]) {
                        throw new ParseException('Complex mappings are not supported.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
                    }
                    // 1-liner optionally followed by newline(s)
                    if (\is_string($value) && $this->lines[0] === trim($value)) {
                        try {
                            $value = Inline::parse($this->lines[0], $flags, $this->refs);
                        } catch (ParseException $e) {
                            $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                            $e->setSnippet($this->currentLine);
                            throw $e;
                        }
                        return $value;
                    }
                    // try to parse the value as a multi-line string as a last resort
                    if (0 === $this->currentLineNb) {
                        $previousLineWasNewline = false;
                        $previousLineWasTerminatedWithBackslash = false;
                        $value = '';
                        foreach ($this->lines as $line) {
                            $trimmedLine = trim($line);
                            if ('#' === ($trimmedLine[0] ?? '')) {
                                continue;
                            }
                            // If the indentation is not consistent at offset 0, it is to be considered as a ParseError
                            if (0 === $this->offset && isset($line[0]) && ' ' === $line[0]) {
                                throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }
                            if (str_contains($line, ': ')) {
                                throw new ParseException('Mapping values are not allowed in multi-line blocks.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }
                            if ('' === $trimmedLine) {
                                $value .= "\n";
                            } elseif (!$previousLineWasNewline && !$previousLineWasTerminatedWithBackslash) {
                                $value .= ' ';
                            }
                            if ('' !== $trimmedLine && str_ends_with($line, '\\')) {
                                $value .= ltrim(substr($line, 0, -1));
                            } elseif ('' !== $trimmedLine) {
                                $value .= $trimmedLine;
                            }
                            if ('' === $trimmedLine) {
                                $previousLineWasNewline = true;
                                $previousLineWasTerminatedWithBackslash = false;
                            } elseif (str_ends_with($line, '\\')) {
                                $previousLineWasNewline = false;
                                $previousLineWasTerminatedWithBackslash = true;
                            } else {
                                $previousLineWasNewline = false;
                                $previousLineWasTerminatedWithBackslash = false;
                            }
                        }
                        try {
                            return Inline::parse(trim($value));
                        } catch (ParseException) {
                            // fall-through to the ParseException thrown below
                        }
                    }
                    throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                }
            } while ($this->moveToNextLine());
            if (null !== $tag) {
                $data = new TaggedValue($tag, $data);
            }
            if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && 'mapping' === $context && !\is_object($data)) {
                $object = new \stdClass();
                foreach ($data as $key => $value) {
                    $object->{$key} = $value;
                }
                $data = $object;
            }
            return $data ?: null;
        }
        private function parseBlock(int $offset, string $yaml, int $flags): mixed
        {
            $skippedLineNumbers = $this->skippedLineNumbers;
            foreach ($this->locallySkippedLineNumbers as $lineNumber) {
                if ($lineNumber < $offset) {
                    continue;
                }
                $skippedLineNumbers[] = $lineNumber;
            }
            $parser = new self();
            $parser->offset = $offset;
            $parser->totalNumberOfLines = $this->totalNumberOfLines;
            $parser->skippedLineNumbers = $skippedLineNumbers;
            $parser->refs = &$this->refs;
            $parser->refsBeingParsed = $this->refsBeingParsed;
            return $parser->doParse($yaml, $flags);
        }
        /**
         * Returns the current line number (takes the offset into account).
         *
         * @internal
         */
        public function getRealCurrentLineNb(): int
        {
            $realCurrentLineNumber = $this->currentLineNb + $this->offset;
            foreach ($this->skippedLineNumbers as $skippedLineNumber) {
                if ($skippedLineNumber > $realCurrentLineNumber) {
                    break;
                }
                ++$realCurrentLineNumber;
            }
            return $realCurrentLineNumber;
        }
        private function getCurrentLineIndentation(): int
        {
            if (' ' !== ($this->currentLine[0] ?? '')) {
                return 0;
            }
            return \strlen($this->currentLine) - \strlen(ltrim($this->currentLine, ' '));
        }
        /**
         * Returns the next embed block of YAML.
         *
         * @param int|null $indentation The indent level at which the block is to be read, or null for default
         * @param bool     $inSequence  True if the enclosing data structure is a sequence
         *
         * @throws ParseException When indentation problem are detected
         */
        private function getNextEmbedBlock(?int $indentation = null, bool $inSequence = false): string
        {
            $oldLineIndentation = $this->getCurrentLineIndentation();
            if (!$this->moveToNextLine()) {
                return '';
            }
            if (null === $indentation) {
                $newIndent = null;
                $movements = 0;
                do {
                    $EOF = false;
                    // empty and comment-like lines do not influence the indentation depth
                    if ($this->isCurrentLineEmpty() || $this->isCurrentLineComment()) {
                        $EOF = !$this->moveToNextLine();
                        if (!$EOF) {
                            ++$movements;
                        }
                    } else {
                        $newIndent = $this->getCurrentLineIndentation();
                    }
                } while (!$EOF && null === $newIndent);
                for ($i = 0; $i < $movements; ++$i) {
                    $this->moveToPreviousLine();
                }
                $unindentedEmbedBlock = $this->isStringUnIndentedCollectionItem();
                if (!$this->isCurrentLineEmpty() && 0 === $newIndent && !$unindentedEmbedBlock) {
                    throw new ParseException('Indentation problem.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                }
            } else {
                $newIndent = $indentation;
            }
            $data = [];
            if ($this->getCurrentLineIndentation() >= $newIndent) {
                $data[] = substr($this->currentLine, $newIndent ?? 0);
            } elseif ($this->isCurrentLineEmpty() || $this->isCurrentLineComment()) {
                $data[] = $this->currentLine;
            } else {
                $this->moveToPreviousLine();
                return '';
            }
            if ($inSequence && $oldLineIndentation === $newIndent && isset($data[0][0]) && '-' === $data[0][0]) {
                // the previous line contained a dash but no item content, this line is a sequence item with the same indentation
                // and therefore no nested list or mapping
                $this->moveToPreviousLine();
                return '';
            }
            $isItUnindentedCollection = $this->isStringUnIndentedCollectionItem();
            $isItComment = $this->isCurrentLineComment();
            while ($this->moveToNextLine()) {
                if ($isItComment && !$isItUnindentedCollection) {
                    $isItUnindentedCollection = $this->isStringUnIndentedCollectionItem();
                    $isItComment = $this->isCurrentLineComment();
                }
                $indent = $this->getCurrentLineIndentation();
                if ($isItUnindentedCollection && !$this->isCurrentLineEmpty() && !$this->isStringUnIndentedCollectionItem() && $newIndent === $indent) {
                    $this->moveToPreviousLine();
                    break;
                }
                if ($this->isCurrentLineBlank()) {
                    $data[] = substr($this->currentLine, $newIndent ?? 0);
                    continue;
                }
                if ($indent >= $newIndent) {
                    $data[] = substr($this->currentLine, $newIndent ?? 0);
                } elseif ($this->isCurrentLineComment()) {
                    $data[] = $this->currentLine;
                } elseif (0 == $indent) {
                    $this->moveToPreviousLine();
                    break;
                } else {
                    throw new ParseException('Indentation problem.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                }
            }
            return implode("\n", $data);
        }
        private function hasMoreLines(): bool
        {
            return \count($this->lines) - 1 > $this->currentLineNb;
        }
        /**
         * Moves the parser to the next line.
         */
        private function moveToNextLine(): bool
        {
            if ($this->currentLineNb >= $this->numberOfParsedLines - 1) {
                return false;
            }
            $this->currentLine = $this->lines[++$this->currentLineNb];
            return true;
        }
        /**
         * Moves the parser to the previous line.
         */
        private function moveToPreviousLine(): bool
        {
            if ($this->currentLineNb < 1) {
                return false;
            }
            $this->currentLine = $this->lines[--$this->currentLineNb];
            return true;
        }
        /**
         * Parses a YAML value.
         *
         * @param string $value   A YAML value
         * @param int    $flags   A bit field of Yaml::PARSE_* constants to customize the YAML parser behavior
         * @param string $context The parser context (either sequence or mapping)
         *
         * @throws ParseException When reference does not exist
         */
        private function parseValue(string $value, int $flags, string $context): mixed
        {
            if (str_starts_with($value, '*')) {
                if (false !== ($pos = strpos($value, '#'))) {
                    $value = substr($value, 1, $pos - 2);
                } else {
                    $value = substr($value, 1);
                }
                if (!\array_key_exists($value, $this->refs)) {
                    if (false !== ($pos = array_search($value, $this->refsBeingParsed, true))) {
                        throw new ParseException(\sprintf('Circular reference [%s] detected for reference "%s".', implode(', ', array_merge(\array_slice($this->refsBeingParsed, $pos), [$value])), $value), $this->currentLineNb + 1, $this->currentLine, $this->filename);
                    }
                    throw new ParseException(\sprintf('Reference "%s" does not exist.', $value), $this->currentLineNb + 1, $this->currentLine, $this->filename);
                }
                return $this->refs[$value];
            }
            if (\in_array($value[0], ['!', '|', '>'], true) && self::preg_match('/^(?:' . self::TAG_PATTERN . ' +)?' . self::BLOCK_SCALAR_HEADER_PATTERN . '$/', $value, $matches)) {
                $modifiers = $matches['modifiers'] ?? '';
                $data = $this->parseBlockScalar($matches['separator'], preg_replace('#\\d+#', '', $modifiers), abs((int) $modifiers));
                if ('' !== $matches['tag'] && '!' !== $matches['tag']) {
                    if ('!!binary' === $matches['tag']) {
                        return Inline::evaluateBinaryScalar($data);
                    }
                    return new TaggedValue(substr($matches['tag'], 1), $data);
                }
                return $data;
            }
            try {
                if ('' !== $value && '{' === $value[0]) {
                    $cursor = \strlen(rtrim($this->currentLine)) - \strlen(rtrim($value));
                    return Inline::parse($this->lexInlineMapping($cursor), $flags, $this->refs);
                } elseif ('' !== $value && '[' === $value[0]) {
                    $cursor = \strlen(rtrim($this->currentLine)) - \strlen(rtrim($value));
                    return Inline::parse($this->lexInlineSequence($cursor), $flags, $this->refs);
                }
                switch ($value[0] ?? '') {
                    case '"':
                    case "'":
                        $cursor = \strlen(rtrim($this->currentLine)) - \strlen(rtrim($value));
                        $parsedValue = Inline::parse($this->lexInlineQuotedString($cursor), $flags, $this->refs);
                        if (isset($this->currentLine[$cursor]) && preg_replace('/\\s*(#.*)?$/A', '', substr($this->currentLine, $cursor))) {
                            throw new ParseException(\sprintf('Unexpected characters near "%s".', substr($this->currentLine, $cursor)));
                        }
                        return $parsedValue;
                    default:
                        $lines = [];
                        while ($this->moveToNextLine()) {
                            // unquoted strings end before the first unindented line
                            if (0 === $this->getCurrentLineIndentation()) {
                                $this->moveToPreviousLine();
                                break;
                            }
                            $lines[] = trim($this->currentLine);
                        }
                        for ($i = 0, $linesCount = \count($lines), $previousLineBlank = false; $i < $linesCount; ++$i) {
                            if ('' === $lines[$i]) {
                                $value .= "\n";
                                $previousLineBlank = true;
                            } elseif ($previousLineBlank) {
                                $value .= $lines[$i];
                                $previousLineBlank = false;
                            } else {
                                $value .= ' ' . $lines[$i];
                                $previousLineBlank = false;
                            }
                        }
                        Inline::$parsedLineNumber = $this->getRealCurrentLineNb();
                        $parsedValue = Inline::parse($value, $flags, $this->refs);
                        if ('mapping' === $context && \is_string($parsedValue) && '"' !== $value[0] && "'" !== $value[0] && '[' !== $value[0] && '{' !== $value[0] && '!' !== $value[0] && str_contains($parsedValue, ': ')) {
                            throw new ParseException('A colon cannot be used in an unquoted mapping value.', $this->getRealCurrentLineNb() + 1, $value, $this->filename);
                        }
                        return $parsedValue;
                }
            } catch (ParseException $e) {
                $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                $e->setSnippet($this->currentLine);
                throw $e;
            }
        }
        /**
         * Parses a block scalar.
         *
         * @param string $style       The style indicator that was used to begin this block scalar (| or >)
         * @param string $chomping    The chomping indicator that was used to begin this block scalar (+ or -)
         * @param int    $indentation The indentation indicator that was used to begin this block scalar
         */
        private function parseBlockScalar(string $style, string $chomping = '', int $indentation = 0): string
        {
            $notEOF = $this->moveToNextLine();
            if (!$notEOF) {
                return '';
            }
            $isCurrentLineBlank = $this->isCurrentLineBlank();
            $blockLines = [];
            // leading blank lines are consumed before determining indentation
            while ($notEOF && $isCurrentLineBlank) {
                // newline only if not EOF
                if ($notEOF = $this->moveToNextLine()) {
                    $blockLines[] = '';
                    $isCurrentLineBlank = $this->isCurrentLineBlank();
                }
            }
            // determine indentation if not specified
            if (0 === $indentation) {
                $currentLineLength = \strlen($this->currentLine);
                for ($i = 0; $i < $currentLineLength && ' ' === $this->currentLine[$i]; ++$i) {
                    ++$indentation;
                }
            }
            if ($indentation > 0) {
                $pattern = \sprintf('/^ {%d}(.*)$/', $indentation);
                while ($notEOF && ($isCurrentLineBlank || self::preg_match($pattern, $this->currentLine, $matches))) {
                    if ($isCurrentLineBlank && \strlen($this->currentLine) > $indentation) {
                        $blockLines[] = substr($this->currentLine, $indentation);
                    } elseif ($isCurrentLineBlank) {
                        $blockLines[] = '';
                    } else {
                        $blockLines[] = $matches[1];
                    }
                    // newline only if not EOF
                    if ($notEOF = $this->moveToNextLine()) {
                        $isCurrentLineBlank = $this->isCurrentLineBlank();
                    }
                }
            } elseif ($notEOF) {
                $blockLines[] = '';
            }
            if ($notEOF) {
                $blockLines[] = '';
                $this->moveToPreviousLine();
            } elseif (!$this->isCurrentLineLastLineInDocument()) {
                $blockLines[] = '';
            }
            // folded style
            if ('>' === $style) {
                $text = '';
                $previousLineIndented = false;
                $previousLineBlank = false;
                for ($i = 0, $blockLinesCount = \count($blockLines); $i < $blockLinesCount; ++$i) {
                    if ('' === $blockLines[$i]) {
                        $text .= "\n";
                        $previousLineIndented = false;
                        $previousLineBlank = true;
                    } elseif (' ' === $blockLines[$i][0]) {
                        $text .= "\n" . $blockLines[$i];
                        $previousLineIndented = true;
                        $previousLineBlank = false;
                    } elseif ($previousLineIndented) {
                        $text .= "\n" . $blockLines[$i];
                        $previousLineIndented = false;
                        $previousLineBlank = false;
                    } elseif ($previousLineBlank || 0 === $i) {
                        $text .= $blockLines[$i];
                        $previousLineIndented = false;
                        $previousLineBlank = false;
                    } else {
                        $text .= ' ' . $blockLines[$i];
                        $previousLineIndented = false;
                        $previousLineBlank = false;
                    }
                }
            } else {
                $text = implode("\n", $blockLines);
            }
            // deal with trailing newlines
            if ('' === $chomping) {
                $text = preg_replace('/\\n+$/', "\n", $text);
            } elseif ('-' === $chomping) {
                $text = preg_replace('/\\n+$/', '', $text);
            }
            return $text;
        }
        /**
         * Returns true if the next line is indented.
         */
        private function isNextLineIndented(): bool
        {
            $currentIndentation = $this->getCurrentLineIndentation();
            $movements = 0;
            do {
                $EOF = !$this->moveToNextLine();
                if (!$EOF) {
                    ++$movements;
                }
            } while (!$EOF && ($this->isCurrentLineEmpty() || $this->isCurrentLineComment()));
            if ($EOF) {
                for ($i = 0; $i < $movements; ++$i) {
                    $this->moveToPreviousLine();
                }
                return false;
            }
            $ret = $this->getCurrentLineIndentation() > $currentIndentation;
            for ($i = 0; $i < $movements; ++$i) {
                $this->moveToPreviousLine();
            }
            return $ret;
        }
        private function isCurrentLineEmpty(): bool
        {
            return $this->isCurrentLineBlank() || $this->isCurrentLineComment();
        }
        private function isCurrentLineBlank(): bool
        {
            return '' === $this->currentLine || '' === trim($this->currentLine, ' ');
        }
        private function isCurrentLineComment(): bool
        {
            // checking explicitly the first char of the trim is faster than loops or strpos
            $ltrimmedLine = '' !== $this->currentLine && ' ' === $this->currentLine[0] ? ltrim($this->currentLine, ' ') : $this->currentLine;
            return '' !== $ltrimmedLine && '#' === $ltrimmedLine[0];
        }
        private function isCurrentLineLastLineInDocument(): bool
        {
            return $this->offset + $this->currentLineNb >= $this->totalNumberOfLines - 1;
        }
        private function cleanup(string $value): string
        {
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            // strip YAML header
            $count = 0;
            $value = preg_replace('#^\\%YAML[: ][\\d\\.]+.*\\n#u', '', $value, -1, $count);
            $this->offset += $count;
            // remove leading comments
            $trimmedValue = preg_replace('#^(\\#.*?\\n)+#s', '', $value, -1, $count);
            if (1 === $count) {
                // items have been removed, update the offset
                $this->offset += substr_count($value, "\n") - substr_count($trimmedValue, "\n");
                $value = $trimmedValue;
            }
            // remove start of the document marker (---)
            $trimmedValue = preg_replace('#^\\-\\-\\-.*?\\n#s', '', $value, -1, $count);
            if (1 === $count) {
                // items have been removed, update the offset
                $this->offset += substr_count($value, "\n") - substr_count($trimmedValue, "\n");
                $value = $trimmedValue;
                // remove end of the document marker (...)
                $value = preg_replace('#\\.\\.\\.\\s*$#', '', $value);
            }
            return $value;
        }
        private function isNextLineUnIndentedCollection(): bool
        {
            $currentIndentation = $this->getCurrentLineIndentation();
            $movements = 0;
            do {
                $EOF = !$this->moveToNextLine();
                if (!$EOF) {
                    ++$movements;
                }
            } while (!$EOF && ($this->isCurrentLineEmpty() || $this->isCurrentLineComment()));
            if ($EOF) {
                return false;
            }
            $ret = $this->getCurrentLineIndentation() === $currentIndentation && $this->isStringUnIndentedCollectionItem();
            for ($i = 0; $i < $movements; ++$i) {
                $this->moveToPreviousLine();
            }
            return $ret;
        }
        private function isStringUnIndentedCollectionItem(): bool
        {
            return '-' === rtrim($this->currentLine) || str_starts_with($this->currentLine, '- ');
        }
        /**
         * A local wrapper for "preg_match" which will throw a ParseException if there
         * is an internal error in the PCRE engine.
         *
         * This avoids us needing to check for "false" every time PCRE is used
         * in the YAML engine
         *
         * @throws ParseException on a PCRE internal error
         *
         * @internal
         */
        public static function preg_match(string $pattern, string $subject, ?array &$matches = null, int $flags = 0, int $offset = 0): int
        {
            if (false === ($ret = preg_match($pattern, $subject, $matches, $flags, $offset))) {
                throw new ParseException(preg_last_error_msg());
            }
            return $ret;
        }
        /**
         * Trim the tag on top of the value.
         *
         * Prevent values such as "!foo {quz: bar}" to be considered as
         * a mapping block.
         */
        private function trimTag(string $value): string
        {
            if ('!' === $value[0]) {
                return ltrim(substr($value, 1, strcspn($value, " \r\n", 1)), ' ');
            }
            return $value;
        }
        private function getLineTag(string $value, int $flags, bool $nextLineCheck = true): ?string
        {
            if ('' === $value || '!' !== $value[0] || 1 !== self::preg_match('/^' . self::TAG_PATTERN . ' *( +#.*)?$/', $value, $matches)) {
                return null;
            }
            if ($nextLineCheck && !$this->isNextLineIndented()) {
                return null;
            }
            $tag = substr($matches['tag'], 1);
            // Built-in tags
            if ($tag && '!' === $tag[0]) {
                throw new ParseException(\sprintf('The built-in tag "!%s" is not implemented.', $tag), $this->getRealCurrentLineNb() + 1, $value, $this->filename);
            }
            if (Yaml::PARSE_CUSTOM_TAGS & $flags) {
                return $tag;
            }
            throw new ParseException(\sprintf('Tags support is not enabled. You must use the flag "Yaml::PARSE_CUSTOM_TAGS" to use "%s".', $matches['tag']), $this->getRealCurrentLineNb() + 1, $value, $this->filename);
        }
        private function lexInlineQuotedString(int &$cursor = 0): string
        {
            $quotation = $this->currentLine[$cursor];
            $value = $quotation;
            ++$cursor;
            $previousLineWasNewline = true;
            $previousLineWasTerminatedWithBackslash = false;
            $lineNumber = 0;
            do {
                if (++$lineNumber > 1) {
                    $cursor += strspn($this->currentLine, ' ', $cursor);
                }
                if ($this->isCurrentLineBlank()) {
                    $value .= "\n";
                } elseif (!$previousLineWasNewline && !$previousLineWasTerminatedWithBackslash) {
                    $value .= ' ';
                }
                for (; \strlen($this->currentLine) > $cursor; ++$cursor) {
                    switch ($this->currentLine[$cursor]) {
                        case '\\':
                            if ("'" === $quotation) {
                                $value .= '\\';
                            } elseif (isset($this->currentLine[++$cursor])) {
                                $value .= '\\' . $this->currentLine[$cursor];
                            }
                            break;
                        case $quotation:
                            ++$cursor;
                            if ("'" === $quotation && isset($this->currentLine[$cursor]) && "'" === $this->currentLine[$cursor]) {
                                $value .= "''";
                                break;
                            }
                            return $value . $quotation;
                        default:
                            $value .= $this->currentLine[$cursor];
                    }
                }
                if ($this->isCurrentLineBlank()) {
                    $previousLineWasNewline = true;
                    $previousLineWasTerminatedWithBackslash = false;
                } elseif ('\\' === $this->currentLine[-1]) {
                    $previousLineWasNewline = false;
                    $previousLineWasTerminatedWithBackslash = true;
                } else {
                    $previousLineWasNewline = false;
                    $previousLineWasTerminatedWithBackslash = false;
                }
                if ($this->hasMoreLines()) {
                    $cursor = 0;
                }
            } while ($this->moveToNextLine());
            throw new ParseException('Malformed inline YAML string.');
        }
        private function lexUnquotedString(int &$cursor): string
        {
            $offset = $cursor;
            $cursor += strcspn($this->currentLine, '[]{},: ', $cursor);
            if ($cursor === $offset) {
                throw new ParseException('Malformed unquoted YAML string.');
            }
            return substr($this->currentLine, $offset, $cursor - $offset);
        }
        private function lexInlineMapping(int &$cursor = 0): string
        {
            return $this->lexInlineStructure($cursor, '}');
        }
        private function lexInlineSequence(int &$cursor = 0): string
        {
            return $this->lexInlineStructure($cursor, ']');
        }
        private function lexInlineStructure(int &$cursor, string $closingTag): string
        {
            $value = $this->currentLine[$cursor];
            ++$cursor;
            do {
                $this->consumeWhitespaces($cursor);
                while (isset($this->currentLine[$cursor])) {
                    switch ($this->currentLine[$cursor]) {
                        case '"':
                        case "'":
                            $value .= $this->lexInlineQuotedString($cursor);
                            break;
                        case ':':
                        case ',':
                            $value .= $this->currentLine[$cursor];
                            ++$cursor;
                            break;
                        case '{':
                            $value .= $this->lexInlineMapping($cursor);
                            break;
                        case '[':
                            $value .= $this->lexInlineSequence($cursor);
                            break;
                        case $closingTag:
                            $value .= $this->currentLine[$cursor];
                            ++$cursor;
                            return $value;
                        case '#':
                            break 2;
                        default:
                            $value .= $this->lexUnquotedString($cursor);
                    }
                    if ($this->consumeWhitespaces($cursor)) {
                        $value .= ' ';
                    }
                }
                if ($this->hasMoreLines()) {
                    $cursor = 0;
                }
            } while ($this->moveToNextLine());
            throw new ParseException('Malformed inline YAML string.');
        }
        private function consumeWhitespaces(int &$cursor): bool
        {
            $whitespacesConsumed = 0;
            do {
                $whitespaceOnlyTokenLength = strspn($this->currentLine, ' ', $cursor);
                $whitespacesConsumed += $whitespaceOnlyTokenLength;
                $cursor += $whitespaceOnlyTokenLength;
                if (isset($this->currentLine[$cursor])) {
                    return 0 < $whitespacesConsumed;
                }
                if ($this->hasMoreLines()) {
                    $cursor = 0;
                }
            } while ($this->moveToNextLine());
            return 0 < $whitespacesConsumed;
        }
    }
}

namespace Symfony\Component\Yaml {

    use Symfony\Component\Yaml\Exception\DumpException;
    use Symfony\Component\Yaml\Exception\ParseException;
    use Symfony\Component\Yaml\Tag\TaggedValue;

    /**
     * Inline implements a YAML parser/dumper for the YAML inline syntax.
     *
     * @author Fabien Potencier <fabien@symfony.com>
     *
     * @internal
     */
    class Inline
    {
        public const REGEX_QUOTED_STRING = '(?:"([^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+)"|\'([^\']*+(?:\'\'[^\']*+)*+)\')';
        public static int $parsedLineNumber = -1;
        public static ?string $parsedFilename = null;
        private static bool $exceptionOnInvalidType = false;
        private static bool $objectSupport = false;
        private static bool $objectForMap = false;
        private static bool $constantSupport = false;
        public static function initialize(int $flags, ?int $parsedLineNumber = null, ?string $parsedFilename = null): void
        {
            self::$exceptionOnInvalidType = (bool) (Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE & $flags);
            self::$objectSupport = (bool) (Yaml::PARSE_OBJECT & $flags);
            self::$objectForMap = (bool) (Yaml::PARSE_OBJECT_FOR_MAP & $flags);
            self::$constantSupport = (bool) (Yaml::PARSE_CONSTANT & $flags);
            self::$parsedFilename = $parsedFilename;
            if (null !== $parsedLineNumber) {
                self::$parsedLineNumber = $parsedLineNumber;
            }
        }
        /**
         * Converts a YAML string to a PHP value.
         *
         * @param int   $flags      A bit field of Yaml::PARSE_* constants to customize the YAML parser behavior
         * @param array $references Mapping of variable names to values
         *
         * @throws ParseException
         */
        public static function parse(string $value, int $flags = 0, array &$references = []): mixed
        {
            self::initialize($flags);
            $value = trim($value);
            if ('' === $value) {
                return '';
            }
            $i = 0;
            $tag = self::parseTag($value, $i, $flags);
            switch ($value[$i]) {
                case '[':
                    $result = self::parseSequence($value, $flags, $i, $references);
                    ++$i;
                    break;
                case '{':
                    $result = self::parseMapping($value, $flags, $i, $references);
                    ++$i;
                    break;
                default:
                    $result = self::parseScalar($value, $flags, null, $i, true, $references);
            }
            // some comments are allowed at the end
            if (preg_replace('/\\s*#.*$/A', '', substr($value, $i))) {
                throw new ParseException(\sprintf('Unexpected characters near "%s".', substr($value, $i)), self::$parsedLineNumber + 1, $value, self::$parsedFilename);
            }
            if (null !== $tag && '' !== $tag) {
                return new TaggedValue($tag, $result);
            }
            return $result;
        }
        /**
         * Dumps a given PHP variable to a YAML string.
         *
         * @param mixed $value The PHP variable to convert
         * @param int   $flags A bit field of Yaml::DUMP_* constants to customize the dumped YAML string
         *
         * @throws DumpException When trying to dump PHP resource
         */
        public static function dump(mixed $value, int $flags = 0): string
        {
            switch (true) {
                case \is_resource($value):
                    if (Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE & $flags) {
                        throw new DumpException(\sprintf('Unable to dump PHP resources in a YAML file ("%s").', get_resource_type($value)));
                    }
                    return self::dumpNull($flags);
                case $value instanceof \DateTimeInterface:
                    return $value->format(match (true) {
                        !($length = \strlen(rtrim($value->format('u'), '0'))) => 'c',
                        $length < 4 => 'Y-m-d\\TH:i:s.vP',
                        default => 'Y-m-d\\TH:i:s.uP',
                    });
                case $value instanceof \UnitEnum:
                    return \sprintf('!php/enum %s::%s', $value::class, $value->name);
                case \is_object($value):
                    if ($value instanceof TaggedValue) {
                        return '!' . $value->getTag() . ' ' . self::dump($value->getValue(), $flags);
                    }
                    if (Yaml::DUMP_OBJECT & $flags) {
                        return '!php/object ' . self::dump(serialize($value));
                    }
                    if (Yaml::DUMP_OBJECT_AS_MAP & $flags && ($value instanceof \stdClass || $value instanceof \ArrayObject)) {
                        return self::dumpHashArray($value, $flags);
                    }
                    if (Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE & $flags) {
                        throw new DumpException('Object support when dumping a YAML file has been disabled.');
                    }
                    return self::dumpNull($flags);
                case \is_array($value):
                    return self::dumpArray($value, $flags);
                case null === $value:
                    return self::dumpNull($flags);
                case true === $value:
                    return 'true';
                case false === $value:
                    return 'false';
                case \is_int($value):
                    return $value;
                case is_numeric($value) && false === strpbrk($value, "\f\n\r\t\v"):
                    $locale = setlocale(\LC_NUMERIC, 0);
                    if (false !== $locale) {
                        setlocale(\LC_NUMERIC, 'C');
                    }
                    if (\is_float($value)) {
                        $repr = (string) $value;
                        if (is_infinite($value)) {
                            $repr = str_ireplace('INF', '.Inf', $repr);
                        } elseif (floor($value) == $value && $repr == $value) {
                            // Preserve float data type since storing a whole number will result in integer value.
                            if (!str_contains($repr, 'E')) {
                                $repr .= '.0';
                            }
                        }
                    } else {
                        $repr = \is_string($value) ? "'{$value}'" : (string) $value;
                    }
                    if (false !== $locale) {
                        setlocale(\LC_NUMERIC, $locale);
                    }
                    return $repr;
                case '' == $value:
                    return "''";
                case self::isBinaryString($value):
                    return '!!binary ' . base64_encode($value);
                case Escaper::requiresDoubleQuoting($value):
                    return Escaper::escapeWithDoubleQuotes($value);
                case Escaper::requiresSingleQuoting($value):
                    $singleQuoted = Escaper::escapeWithSingleQuotes($value);
                    if (!str_contains($value, "'")) {
                        return $singleQuoted;
                    }
                    // Attempt double-quoting the string instead to see if it's more efficient.
                    $doubleQuoted = Escaper::escapeWithDoubleQuotes($value);
                    return \strlen($doubleQuoted) < \strlen($singleQuoted) ? $doubleQuoted : $singleQuoted;
                case Parser::preg_match('{^[0-9]+[_0-9]*$}', $value):
                case Parser::preg_match(self::getHexRegex(), $value):
                case Parser::preg_match(self::getTimestampRegex(), $value):
                    return Escaper::escapeWithSingleQuotes($value);
                default:
                    return $value;
            }
        }
        /**
         * Check if given array is hash or just normal indexed array.
         */
        public static function isHash(array|\ArrayObject|\stdClass $value): bool
        {
            if ($value instanceof \stdClass || $value instanceof \ArrayObject) {
                return true;
            }
            $expectedKey = 0;
            foreach ($value as $key => $val) {
                if ($key !== $expectedKey++) {
                    return true;
                }
            }
            return false;
        }
        /**
         * Dumps a PHP array to a YAML string.
         *
         * @param array $value The PHP array to dump
         * @param int   $flags A bit field of Yaml::DUMP_* constants to customize the dumped YAML string
         */
        private static function dumpArray(array $value, int $flags): string
        {
            // array
            if (($value || Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE & $flags) && !self::isHash($value)) {
                $output = [];
                foreach ($value as $val) {
                    $output[] = self::dump($val, $flags);
                }
                return \sprintf('[%s]', implode(', ', $output));
            }
            return self::dumpHashArray($value, $flags);
        }
        /**
         * Dumps hash array to a YAML string.
         *
         * @param array|\ArrayObject|\stdClass $value The hash array to dump
         * @param int                          $flags A bit field of Yaml::DUMP_* constants to customize the dumped YAML string
         */
        private static function dumpHashArray(array|\ArrayObject|\stdClass $value, int $flags): string
        {
            $output = [];
            foreach ($value as $key => $val) {
                if (\is_int($key) && Yaml::DUMP_NUMERIC_KEY_AS_STRING & $flags) {
                    $key = (string) $key;
                }
                $output[] = \sprintf('%s: %s', self::dump($key, $flags), self::dump($val, $flags));
            }
            return \sprintf('{ %s }', implode(', ', $output));
        }
        private static function dumpNull(int $flags): string
        {
            if (Yaml::DUMP_NULL_AS_TILDE & $flags) {
                return '~';
            }
            return 'null';
        }
        /**
         * Parses a YAML scalar.
         *
         * @throws ParseException When malformed inline YAML string is parsed
         */
        public static function parseScalar(string $scalar, int $flags = 0, ?array $delimiters = null, int &$i = 0, bool $evaluate = true, array &$references = [], ?bool &$isQuoted = null): mixed
        {
            if (\in_array($scalar[$i], ['"', "'"], true)) {
                // quoted scalar
                $isQuoted = true;
                $output = self::parseQuotedScalar($scalar, $i);
                if (null !== $delimiters) {
                    $tmp = ltrim(substr($scalar, $i), " \n");
                    if ('' === $tmp) {
                        throw new ParseException(\sprintf('Unexpected end of line, expected one of "%s".', implode('', $delimiters)), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                    }
                    if (!\in_array($tmp[0], $delimiters)) {
                        throw new ParseException(\sprintf('Unexpected characters (%s).', substr($scalar, $i)), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                    }
                }
            } else {
                // "normal" string
                $isQuoted = false;
                if (!$delimiters) {
                    $output = substr($scalar, $i);
                    $i += \strlen($output);
                    // remove comments
                    if (Parser::preg_match('/[ \\t]+#/', $output, $match, \PREG_OFFSET_CAPTURE)) {
                        $output = substr($output, 0, $match[0][1]);
                    }
                } elseif (Parser::preg_match('/^(.*?)(' . implode('|', $delimiters) . ')/', substr($scalar, $i), $match)) {
                    $output = $match[1];
                    $i += \strlen($output);
                    $output = trim($output);
                } else {
                    throw new ParseException(\sprintf('Malformed inline YAML string: "%s".', $scalar), self::$parsedLineNumber + 1, null, self::$parsedFilename);
                }
                // a non-quoted string cannot start with @ or ` (reserved) nor with a scalar indicator (| or >)
                if ($output && ('@' === $output[0] || '`' === $output[0] || '|' === $output[0] || '>' === $output[0] || '%' === $output[0])) {
                    throw new ParseException(\sprintf('The reserved indicator "%s" cannot start a plain scalar; you need to quote the scalar.', $output[0]), self::$parsedLineNumber + 1, $output, self::$parsedFilename);
                }
                if ($evaluate) {
                    $output = self::evaluateScalar($output, $flags, $references, $isQuoted);
                }
            }
            return $output;
        }
        /**
         * Parses a YAML quoted scalar.
         *
         * @throws ParseException When malformed inline YAML string is parsed
         */
        private static function parseQuotedScalar(string $scalar, int &$i = 0): string
        {
            if (!Parser::preg_match('/' . self::REGEX_QUOTED_STRING . '/Au', substr($scalar, $i), $match)) {
                throw new ParseException(\sprintf('Malformed inline YAML string: "%s".', substr($scalar, $i)), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
            }
            $output = substr($match[0], 1, -1);
            $unescaper = new Unescaper();
            if ('"' == $scalar[$i]) {
                $output = $unescaper->unescapeDoubleQuotedString($output);
            } else {
                $output = $unescaper->unescapeSingleQuotedString($output);
            }
            $i += \strlen($match[0]);
            return $output;
        }
        /**
         * Parses a YAML sequence.
         *
         * @throws ParseException When malformed inline YAML string is parsed
         */
        private static function parseSequence(string $sequence, int $flags, int &$i = 0, array &$references = []): array
        {
            $output = [];
            $len = \strlen($sequence);
            ++$i;
            // [foo, bar, ...]
            $lastToken = null;
            while ($i < $len) {
                if (']' === $sequence[$i]) {
                    return $output;
                }
                if (',' === $sequence[$i] || ' ' === $sequence[$i]) {
                    if (',' === $sequence[$i] && (null === $lastToken || 'separator' === $lastToken)) {
                        $output[] = null;
                    } elseif (',' === $sequence[$i]) {
                        $lastToken = 'separator';
                    }
                    ++$i;
                    continue;
                }
                $tag = self::parseTag($sequence, $i, $flags);
                switch ($sequence[$i]) {
                    case '[':
                        // nested sequence
                        $value = self::parseSequence($sequence, $flags, $i, $references);
                        break;
                    case '{':
                        // nested mapping
                        $value = self::parseMapping($sequence, $flags, $i, $references);
                        break;
                    default:
                        $value = self::parseScalar($sequence, $flags, [',', ']'], $i, null === $tag, $references, $isQuoted);
                        // the value can be an array if a reference has been resolved to an array var
                        if (\is_string($value) && !$isQuoted && str_contains($value, ': ')) {
                            // embedded mapping?
                            try {
                                $pos = 0;
                                $value = self::parseMapping('{' . $value . '}', $flags, $pos, $references);
                            } catch (\InvalidArgumentException) {
                                // no, it's not
                            }
                        }
                        if (!$isQuoted && \is_string($value) && '' !== $value && '&' === $value[0] && Parser::preg_match(Parser::REFERENCE_PATTERN, $value, $matches)) {
                            $references[$matches['ref']] = $matches['value'];
                            $value = $matches['value'];
                        }
                        --$i;
                }
                if (null !== $tag && '' !== $tag) {
                    $value = new TaggedValue($tag, $value);
                }
                $output[] = $value;
                $lastToken = 'value';
                ++$i;
            }
            throw new ParseException(\sprintf('Malformed inline YAML string: "%s".', $sequence), self::$parsedLineNumber + 1, null, self::$parsedFilename);
        }
        /**
         * Parses a YAML mapping.
         *
         * @throws ParseException When malformed inline YAML string is parsed
         */
        private static function parseMapping(string $mapping, int $flags, int &$i = 0, array &$references = []): array|\stdClass
        {
            $output = [];
            $len = \strlen($mapping);
            ++$i;
            $allowOverwrite = false;
            // {foo: bar, bar:foo, ...}
            while ($i < $len) {
                switch ($mapping[$i]) {
                    case ' ':
                    case ',':
                    case "\n":
                        ++$i;
                        continue 2;
                    case '}':
                        if (self::$objectForMap) {
                            return (object) $output;
                        }
                        return $output;
                }
                // key
                $offsetBeforeKeyParsing = $i;
                $isKeyQuoted = \in_array($mapping[$i], ['"', "'"], true);
                $key = self::parseScalar($mapping, $flags, [':', ' '], $i, false);
                if ($offsetBeforeKeyParsing === $i) {
                    throw new ParseException('Missing mapping key.', self::$parsedLineNumber + 1, $mapping);
                }
                if ('!php/const' === $key || '!php/enum' === $key) {
                    $key .= ' ' . self::parseScalar($mapping, $flags, [':'], $i, false);
                    $key = self::evaluateScalar($key, $flags);
                }
                if (false === ($i = strpos($mapping, ':', $i))) {
                    break;
                }
                if (!$isKeyQuoted) {
                    $evaluatedKey = self::evaluateScalar($key, $flags, $references);
                    if ('' !== $key && $evaluatedKey !== $key && !\is_string($evaluatedKey) && !\is_int($evaluatedKey)) {
                        throw new ParseException('Implicit casting of incompatible mapping keys to strings is not supported. Quote your evaluable mapping keys instead.', self::$parsedLineNumber + 1, $mapping);
                    }
                }
                if (!$isKeyQuoted && (!isset($mapping[$i + 1]) || !\in_array($mapping[$i + 1], [' ', ',', '[', ']', '{', '}', "\n"], true))) {
                    throw new ParseException('Colons must be followed by a space or an indication character (i.e. " ", ",", "[", "]", "{", "}").', self::$parsedLineNumber + 1, $mapping);
                }
                if ('<<' === $key) {
                    $allowOverwrite = true;
                }
                while ($i < $len) {
                    if (':' === $mapping[$i] || ' ' === $mapping[$i] || "\n" === $mapping[$i]) {
                        ++$i;
                        continue;
                    }
                    $tag = self::parseTag($mapping, $i, $flags);
                    switch ($mapping[$i]) {
                        case '[':
                            // nested sequence
                            $value = self::parseSequence($mapping, $flags, $i, $references);
                            // Spec: Keys MUST be unique; first one wins.
                            // Parser cannot abort this mapping earlier, since lines
                            // are processed sequentially.
                            // But overwriting is allowed when a merge node is used in current block.
                            if ('<<' === $key) {
                                foreach ($value as $parsedValue) {
                                    $output += $parsedValue;
                                }
                            } elseif ($allowOverwrite || !isset($output[$key])) {
                                if (null !== $tag) {
                                    $output[$key] = new TaggedValue($tag, $value);
                                } else {
                                    $output[$key] = $value;
                                }
                            } elseif (isset($output[$key])) {
                                throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), self::$parsedLineNumber + 1, $mapping);
                            }
                            break;
                        case '{':
                            // nested mapping
                            $value = self::parseMapping($mapping, $flags, $i, $references);
                            // Spec: Keys MUST be unique; first one wins.
                            // Parser cannot abort this mapping earlier, since lines
                            // are processed sequentially.
                            // But overwriting is allowed when a merge node is used in current block.
                            if ('<<' === $key) {
                                $output += $value;
                            } elseif ($allowOverwrite || !isset($output[$key])) {
                                if (null !== $tag) {
                                    $output[$key] = new TaggedValue($tag, $value);
                                } else {
                                    $output[$key] = $value;
                                }
                            } elseif (isset($output[$key])) {
                                throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), self::$parsedLineNumber + 1, $mapping);
                            }
                            break;
                        default:
                            $value = self::parseScalar($mapping, $flags, [',', '}', "\n"], $i, null === $tag, $references, $isValueQuoted);
                            // Spec: Keys MUST be unique; first one wins.
                            // Parser cannot abort this mapping earlier, since lines
                            // are processed sequentially.
                            // But overwriting is allowed when a merge node is used in current block.
                            if ('<<' === $key) {
                                $output += $value;
                            } elseif ($allowOverwrite || !isset($output[$key])) {
                                if (!$isValueQuoted && \is_string($value) && '' !== $value && '&' === $value[0] && !self::isBinaryString($value) && Parser::preg_match(Parser::REFERENCE_PATTERN, $value, $matches)) {
                                    $references[$matches['ref']] = $matches['value'];
                                    $value = $matches['value'];
                                }
                                if (null !== $tag) {
                                    $output[$key] = new TaggedValue($tag, $value);
                                } else {
                                    $output[$key] = $value;
                                }
                            } elseif (isset($output[$key])) {
                                throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), self::$parsedLineNumber + 1, $mapping);
                            }
                            --$i;
                    }
                    ++$i;
                    continue 2;
                }
            }
            throw new ParseException(\sprintf('Malformed inline YAML string: "%s".', $mapping), self::$parsedLineNumber + 1, null, self::$parsedFilename);
        }
        /**
         * Evaluates scalars and replaces magic values.
         *
         * @throws ParseException when object parsing support was disabled and the parser detected a PHP object or when a reference could not be resolved
         */
        private static function evaluateScalar(string $scalar, int $flags, array &$references = [], ?bool &$isQuotedString = null): mixed
        {
            $isQuotedString = false;
            $scalar = trim($scalar);
            if (str_starts_with($scalar, '*')) {
                if (false !== ($pos = strpos($scalar, '#'))) {
                    $value = substr($scalar, 1, $pos - 2);
                } else {
                    $value = substr($scalar, 1);
                }
                // an unquoted *
                if ('' === $value) {
                    throw new ParseException('A reference must contain at least one character.', self::$parsedLineNumber + 1, $value, self::$parsedFilename);
                }
                if (!\array_key_exists($value, $references)) {
                    throw new ParseException(\sprintf('Reference "%s" does not exist.', $value), self::$parsedLineNumber + 1, $value, self::$parsedFilename);
                }
                return $references[$value];
            }
            $scalarLower = strtolower($scalar);
            switch (true) {
                case 'null' === $scalarLower:
                case '' === $scalar:
                case '~' === $scalar:
                    return null;
                case 'true' === $scalarLower:
                    return true;
                case 'false' === $scalarLower:
                    return false;
                case '!' === $scalar[0]:
                    switch (true) {
                        case str_starts_with($scalar, '!!str '):
                            $s = substr($scalar, 6);
                            if (\in_array($s[0] ?? '', ['"', "'"], true)) {
                                $isQuotedString = true;
                                $s = self::parseQuotedScalar($s);
                            }
                            return $s;
                        case str_starts_with($scalar, '! '):
                            return substr($scalar, 2);
                        case str_starts_with($scalar, '!php/object'):
                            if (self::$objectSupport) {
                                if (!isset($scalar[12])) {
                                    throw new ParseException('Missing value for tag "!php/object".', self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                                }
                                return unserialize(self::parseScalar(substr($scalar, 12)));
                            }
                            if (self::$exceptionOnInvalidType) {
                                throw new ParseException('Object support when parsing a YAML file has been disabled.', self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                            }
                            return null;
                        case str_starts_with($scalar, '!php/const'):
                            if (self::$constantSupport) {
                                if (!isset($scalar[11])) {
                                    throw new ParseException('Missing value for tag "!php/const".', self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                                }
                                $i = 0;
                                if (\defined($const = self::parseScalar(substr($scalar, 11), 0, null, $i, false))) {
                                    return \constant($const);
                                }
                                throw new ParseException(\sprintf('The constant "%s" is not defined.', $const), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                            }
                            if (self::$exceptionOnInvalidType) {
                                throw new ParseException(\sprintf('The string "%s" could not be parsed as a constant. Did you forget to pass the "Yaml::PARSE_CONSTANT" flag to the parser?', $scalar), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                            }
                            return null;
                        case str_starts_with($scalar, '!php/enum'):
                            if (self::$constantSupport) {
                                if (!isset($scalar[11])) {
                                    throw new ParseException('Missing value for tag "!php/enum".', self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                                }
                                $i = 0;
                                $enumName = self::parseScalar(substr($scalar, 10), 0, null, $i, false);
                                $useName = str_contains($enumName, '::');
                                $enum = $useName ? strstr($enumName, '::', true) : $enumName;
                                if (!enum_exists($enum)) {
                                    throw new ParseException(\sprintf('The enum "%s" is not defined.', $enum), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                                }
                                if (!$useName) {
                                    return $enum::cases();
                                }
                                if ($useValue = str_ends_with($enumName, '->value')) {
                                    $enumName = substr($enumName, 0, -7);
                                }
                                if (!\defined($enumName)) {
                                    throw new ParseException(\sprintf('The string "%s" is not the name of a valid enum.', $enumName), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                                }
                                $value = \constant($enumName);
                                if (!$useValue) {
                                    return $value;
                                }
                                if (!$value instanceof \BackedEnum) {
                                    throw new ParseException(\sprintf('The enum "%s" defines no value next to its name.', $enumName), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                                }
                                return $value->value;
                            }
                            if (self::$exceptionOnInvalidType) {
                                throw new ParseException(\sprintf('The string "%s" could not be parsed as an enum. Did you forget to pass the "Yaml::PARSE_CONSTANT" flag to the parser?', $scalar), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
                            }
                            return null;
                        case str_starts_with($scalar, '!!float '):
                            return (float) substr($scalar, 8);
                        case str_starts_with($scalar, '!!binary '):
                            return self::evaluateBinaryScalar(substr($scalar, 9));
                    }
                    throw new ParseException(\sprintf('The string "%s" could not be parsed as it uses an unsupported built-in tag.', $scalar), self::$parsedLineNumber, $scalar, self::$parsedFilename);
                case preg_match('/^(?:\\+|-)?0o(?P<value>[0-7_]++)$/', $scalar, $matches):
                    $value = str_replace('_', '', $matches['value']);
                    if ('-' === $scalar[0]) {
                        return -octdec($value);
                    }
                    return octdec($value);
                case \in_array($scalar[0], ['+', '-', '.'], true) || is_numeric($scalar[0]):
                    if (Parser::preg_match('{^[+-]?[0-9][0-9_]*$}', $scalar)) {
                        $scalar = str_replace('_', '', $scalar);
                    }
                    switch (true) {
                        case ctype_digit($scalar):
                        case '-' === $scalar[0] && ctype_digit(substr($scalar, 1)):
                            $cast = (int) $scalar;
                            return $scalar === (string) $cast ? $cast : $scalar;
                        case is_numeric($scalar):
                        case Parser::preg_match(self::getHexRegex(), $scalar):
                            $scalar = str_replace('_', '', $scalar);
                            return '0x' === $scalar[0] . $scalar[1] ? hexdec($scalar) : (float) $scalar;
                        case '.inf' === $scalarLower:
                        case '.nan' === $scalarLower:
                            return -log(0);
                        case '-.inf' === $scalarLower:
                            return log(0);
                        case Parser::preg_match('/^(-|\\+)?[0-9][0-9_]*(\\.[0-9_]+)?$/', $scalar):
                            return (float) str_replace('_', '', $scalar);
                        case Parser::preg_match(self::getTimestampRegex(), $scalar):
                            try {
                                // When no timezone is provided in the parsed date, YAML spec says we must assume UTC.
                                $time = new \DateTimeImmutable($scalar, new \DateTimeZone('UTC'));
                            } catch (\Exception $e) {
                                // Some dates accepted by the regex are not valid dates.
                                throw new ParseException(\sprintf('The date "%s" could not be parsed as it is an invalid date.', $scalar), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename, $e);
                            }
                            if (Yaml::PARSE_DATETIME & $flags) {
                                return $time;
                            }
                            if ('' !== rtrim($time->format('u'), '0')) {
                                return (float) $time->format('U.u');
                            }
                            try {
                                if (false !== ($scalar = $time->getTimestamp())) {
                                    return $scalar;
                                }
                            } catch (\ValueError) {
                                // no-op
                            }
                            return $time->format('U');
                    }
            }
            return (string) $scalar;
        }
        private static function parseTag(string $value, int &$i, int $flags): ?string
        {
            if ('!' !== $value[$i]) {
                return null;
            }
            $tagLength = strcspn($value, " \t\n[]{},", $i + 1);
            $tag = substr($value, $i + 1, $tagLength);
            $nextOffset = $i + $tagLength + 1;
            $nextOffset += strspn($value, ' ', $nextOffset);
            if ('' === $tag && (!isset($value[$nextOffset]) || \in_array($value[$nextOffset], [']', '}', ','], true))) {
                throw new ParseException('Using the unquoted scalar value "!" is not supported. You must quote it.', self::$parsedLineNumber + 1, $value, self::$parsedFilename);
            }
            // Is followed by a scalar and is a built-in tag
            if ('' !== $tag && (!isset($value[$nextOffset]) || !\in_array($value[$nextOffset], ['[', '{'], true)) && ('!' === $tag[0] || \in_array($tag, ['str', 'php/const', 'php/enum', 'php/object'], true))) {
                // Manage in {@link self::evaluateScalar()}
                return null;
            }
            $i = $nextOffset;
            // Built-in tags
            if ('' !== $tag && '!' === $tag[0]) {
                throw new ParseException(\sprintf('The built-in tag "!%s" is not implemented.', $tag), self::$parsedLineNumber + 1, $value, self::$parsedFilename);
            }
            if ('' !== $tag && !isset($value[$i])) {
                throw new ParseException(\sprintf('Missing value for tag "%s".', $tag), self::$parsedLineNumber + 1, $value, self::$parsedFilename);
            }
            if ('' === $tag || Yaml::PARSE_CUSTOM_TAGS & $flags) {
                return $tag;
            }
            throw new ParseException(\sprintf('Tags support is not enabled. Enable the "Yaml::PARSE_CUSTOM_TAGS" flag to use "!%s".', $tag), self::$parsedLineNumber + 1, $value, self::$parsedFilename);
        }
        public static function evaluateBinaryScalar(string $scalar): string
        {
            $parsedBinaryData = self::parseScalar(preg_replace('/\\s/', '', $scalar));
            if (0 !== \strlen($parsedBinaryData) % 4) {
                throw new ParseException(\sprintf('The normalized base64 encoded data (data without whitespace characters) length must be a multiple of four (%d bytes given).', \strlen($parsedBinaryData)), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
            }
            if (!Parser::preg_match('#^[A-Z0-9+/]+={0,2}$#i', $parsedBinaryData)) {
                throw new ParseException(\sprintf('The base64 encoded data (%s) contains invalid characters.', $parsedBinaryData), self::$parsedLineNumber + 1, $scalar, self::$parsedFilename);
            }
            return base64_decode($parsedBinaryData, true);
        }
        private static function isBinaryString(string $value): bool
        {
            return !preg_match('//u', $value) || preg_match('/[^\\x00\\x07-\\x0d\\x1B\\x20-\\xff]/', $value);
        }
        /**
         * Gets a regex that matches a YAML date.
         *
         * @see http://www.yaml.org/spec/1.2/spec.html#id2761573
         */
        private static function getTimestampRegex(): string
        {
            return <<<EOF
        ~^
        (?P<year>[0-9][0-9][0-9][0-9])
        -(?P<month>[0-9][0-9]?)
        -(?P<day>[0-9][0-9]?)
        (?:(?:[Tt]|[ \t]+)
        (?P<hour>[0-9][0-9]?)
        :(?P<minute>[0-9][0-9])
        :(?P<second>[0-9][0-9])
        (?:\\.(?P<fraction>[0-9]*))?
        (?:[ \t]*(?P<tz>Z|(?P<tz_sign>[-+])(?P<tz_hour>[0-9][0-9]?)
        (?::(?P<tz_minute>[0-9][0-9]))?))?)?
        \$~x
EOF;
        }
        /**
         * Gets a regex that matches a YAML number in hexadecimal notation.
         */
        private static function getHexRegex(): string
        {
            return '~^0x[0-9a-f_]++$~i';
        }
    }
}

namespace Symfony\Component\Yaml {

    use Symfony\Component\Yaml\Exception\ParseException;

    /**
     * Unescaper encapsulates unescaping rules for single and double-quoted
     * YAML strings.
     *
     * @author Matthew Lewinski <matthew@lewinski.org>
     *
     * @internal
     */
    class Unescaper
    {
        /**
         * Regex fragment that matches an escaped character in a double quoted string.
         */
        public const REGEX_ESCAPED_CHARACTER = '\\\\(x[0-9a-fA-F]{2}|u[0-9a-fA-F]{4}|U[0-9a-fA-F]{8}|.)';
        /**
         * Unescapes a single quoted string.
         *
         * @param string $value A single quoted string
         */
        public function unescapeSingleQuotedString(string $value): string
        {
            return str_replace('\'\'', '\'', $value);
        }
        /**
         * Unescapes a double quoted string.
         *
         * @param string $value A double quoted string
         */
        public function unescapeDoubleQuotedString(string $value): string
        {
            $callback = fn($match) => $this->unescapeCharacter($match[0]);
            // evaluate the string
            return preg_replace_callback('/' . self::REGEX_ESCAPED_CHARACTER . '/u', $callback, $value);
        }
        /**
         * Unescapes a character that was found in a double-quoted string.
         *
         * @param string $value An escaped character
         */
        private function unescapeCharacter(string $value): string
        {
            return match ($value[1]) {
                '0' => "\x00",
                'a' => "\x07",
                'b' => "\x08",
                't' => "\t",
                "\t" => "\t",
                'n' => "\n",
                'v' => "\v",
                'f' => "\f",
                'r' => "\r",
                'e' => "\x1b",
                ' ' => ' ',
                '"' => '"',
                '/' => '/',
                '\\' => '\\',
                // U+0085 NEXT LINE
                'N' => "",
                // U+00A0 NO-BREAK SPACE
                '_' => " ",
                // U+2028 LINE SEPARATOR
                'L' => "",
                // U+2029 PARAGRAPH SEPARATOR
                'P' => "",
                'x' => self::utf8chr(hexdec(substr($value, 2, 2))),
                'u' => self::utf8chr(hexdec(substr($value, 2, 4))),
                'U' => self::utf8chr(hexdec(substr($value, 2, 8))),
                default => throw new ParseException(\sprintf('Found unknown escape character "%s".', $value)),
            };
        }
        /**
         * Get the UTF-8 character for the given code point.
         */
        private static function utf8chr(int $c): string
        {
            if (0x80 > ($c %= 0x200000)) {
                return \chr($c);
            }
            if (0x800 > $c) {
                return \chr(0xc0 | $c >> 6) . \chr(0x80 | $c & 0x3f);
            }
            if (0x10000 > $c) {
                return \chr(0xe0 | $c >> 12) . \chr(0x80 | $c >> 6 & 0x3f) . \chr(0x80 | $c & 0x3f);
            }
            return \chr(0xf0 | $c >> 18) . \chr(0x80 | $c >> 12 & 0x3f) . \chr(0x80 | $c >> 6 & 0x3f) . \chr(0x80 | $c & 0x3f);
        }
    }
}
