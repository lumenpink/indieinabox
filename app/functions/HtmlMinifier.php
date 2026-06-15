<?php

declare(strict_types=1);

namespace Indieinabox;

class HtmlMinifier
{
    /** @var array<string, mixed> */
    private array $options;
    private string $output;
    /** @var array<int, array<string, string>> */
    private array $build;
    private int $skip;
    private bool $head;
    /** @var array<string, string[]> */
    private array $elements;

    public const PATTERN = '/\s+/';

    /** @param array<string, mixed> $options */
    public function __construct(array $options)
    {
        $this->options = $options;
        $this->output = '';
        $this->build = [];
        $this->skip = 0;
        $this->head = false;
        $this->elements = [
            'skip' => [
                'code',
                'pre',
                'script',
                'textarea',
            ],
            'inline' => [
                'a',
                'abbr',
                'acronym',
                'b',
                'bdo',
                'big',
                'br',
                'cite',
                'code',
                'dfn',
                'em',
                'i',
                'img',
                'kbd',
                'map',
                'object',
                'samp',
                'small',
                'span',
                'strong',
                'sub',
                'sup',
                'tt',
                'var',
                'q',
            ],
            'hard' => [
                '!doctype',
                'body',
                'html',
            ],
        ];
    }

    // Run minifier
    public function minify(string $html): string
    {
        if (
            !isset($this->options['disable_comments'])
            || !$this->options['disable_comments']
        ) {
            $html = $this->removeComments($html);
        }

        $rest = $html;

        while (!empty($rest)) {
            $parts = explode('<', $rest, 2);
            $this->walk($parts[0]);
            $rest = $parts[1] ?? '';
        }

        return $this->output;
    }

    // Walk through html
    private function walk(string &$part): void
    {
        $tagParts = explode('>', $part);
        $tagContent = $tagParts[0];

        if (!empty($tagContent)) {
            $name = $this->findName($tagContent);
            $element = $this->toElement($tagContent, $part, $name);
            $type = $this->toType($element);

            if ($name === 'head') {
                $this->head = $type === 'open';
            }

            $this->build[] = [
                'name' => $name,
                'content' => $element,
                'type' => $type,
            ];

            $this->setSkip($name, $type);

            $content = $tagParts[1] ?? '';
            if ($content !== '') {
                $this->build[] = [
                    'content' => $this->compact($content, $name),
                    'type' => 'content',
                ];
            }

            $this->buildHtml();
        }
    }

    // Remove comments
    private function removeComments(string $content = ''): string
    {
        return preg_replace('/(?=<!--)([\s\S]*?)-->/', '', $content);
    }

    // Check if string contains string
    private function contains(string $needle, string $haystack): bool
    {
        return strpos($haystack, $needle) !== false;
    }

    // Return type of element
    private function toType(string $element): string
    {
        return (substr($element, 1, 1) === '/') ? 'close' : 'open';
    }

    // Create element
    private function toElement(string $element, string $noll, string $name): string
    {
        $element = $this->stripWhitespace($element);
        $element = $this->addChevrons($element, $noll);
        $element = $this->removeSelfSlash($element);
        $element = $this->removeMeta($element, $name);
        return $element;
    }

    // Remove unneeded element meta
    private function removeMeta(string $element, string $name): string
    {
        if ($name === 'style') {
            $element = str_replace(
                [
                    ' type="text/css"',
                    "' type='text/css'",
                ],
                ['', ''],
                $element
            );
        } elseif ($name === 'script') {
            $element = str_replace(
                [
                    ' type="text/javascript"',
                    " type='text/javascript'",
                ],
                ['', ''],
                $element
            );
        }
        return $element;
    }

    // Strip whitespace from element
    private function stripWhitespace(string $element): string
    {
        if ($this->skip === 0) {
            $element = preg_replace(self::PATTERN, ' ', $element);
        }
        return trim($element);
    }

    // Add chevrons around element
    private function addChevrons(string $element, string $noll): string
    {
        if (empty($element)) {
            return $element;
        }
        $char = ($this->contains('>', $noll)) ? '>' : '';
        $element = '<' . $element . $char;
        return $element;
    }

    // Remove unneeded self slash
    private function removeSelfSlash(string $element): string
    {
        if (substr($element, -3) === ' />') {
            $element = substr($element, 0, -3) . '>';
        }
        return $element;
    }

    // Compact content
    private function compact(string $content, string $name): string
    {
        $result = $content;

        if ($this->skip === 0) {
            $result = preg_replace(self::PATTERN, ' ', $content);

            if (!in_array($name, $this->elements['skip'])) {
                $result = (in_array($name, $this->elements['hard']) || $this->head)
                    ? $this->minifyHard($result)
                    : $this->minifyKeepSpaces($result);
            }
        }

        return $result;
    }

    // Build html
    private function buildHtml(): void
    {
        foreach ($this->build as $build) {
            if (!empty($this->options['collapse_whitespace'])) {
                if (strlen(trim($build['content'])) === 0) {
                    continue;
                } elseif ($build['type'] !== 'content' && !in_array($build['name'], $this->elements['inline'])) {
                    $build['content'] = trim($build['content']);
                }
            }

            $this->output .= $build['content'];
        }

        $this->build = [];
    }

    // Find name by part
    private function findName(string $part): string
    {
        $nameCut = explode(" ", $part, 2)[0];
        $nameCut = explode(">", $nameCut, 2)[0];
        $nameCut = explode("\n", $nameCut, 2)[0];
        $nameCut = preg_replace(self::PATTERN, '', $nameCut);
        $nameCut = strtolower(str_replace('/', '', $nameCut));
        return $nameCut;
    }

    // Set skip if elements are blocked from minification
    private function setSkip(string $name, string $type): void
    {
        if (in_array($name, $this->elements['skip'])) {
            if ($type === 'open') {
                $this->skip++;
            }
            if ($type === 'close') {
                $this->skip--;
            }
        }
    }

    // Minify all, even spaces between elements
    private function minifyHard(string $element): string
    {
        $element = preg_replace('!\s+!', ' ', $element);
        $element = trim($element);
        return trim($element);
    }

    // Strip but keep one space
    private function minifyKeepSpaces(string $element): string
    {
        return preg_replace('!\s+!', ' ', $element);
    }
}
