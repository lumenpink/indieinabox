<?php

declare(strict_types=1);

namespace Indieinabox\Parsedown;

final class BlocksParser
{

    /**
     * @var array{
     *   '#': list<string>,
     *   '*': list<string>,
     *   '+': list<string>,
     *   '-': list<string>,
     *   '0': list<string>,
     *   '1': list<string>,
     *   '2': list<string>,
     *   '3': list<string>,
     *   '4': list<string>,
     *   '5': list<string>,
     *   '6': list<string>,
     *   '7': list<string>,
     *   '8': list<string>,
     *   '9': list<string>,
     *   ':': list<string>,
     *   '<': list<string>,
     *   '=': list<string>,
     *   '>': list<string>,
     *   '[': list<string>,
     *   '_': list<string>,
     *   '`': list<string>,
     *   '|': list<string>,
     *   '~': list<string>
     * }
     */
    private array $blockTypes = [
        '#' => ['Header'],
        '*' => ['Rule', 'List'],
        '+' => ['List'],
        '-' => ['SetextHeader', 'Table', 'Rule', 'List'],
        '0' => ['List'],
        '1' => ['List'],
        '2' => ['List'],
        '3' => ['List'],
        '4' => ['List'],
        '5' => ['List'],
        '6' => ['List'],
        '7' => ['List'],
        '8' => ['List'],
        '9' => ['List'],
        ':' => ['Table'],
        '<' => ['Comment', 'Markup'],
        '=' => ['SetextHeader'],
        '>' => ['Quote'],
        '[' => ['Reference'],
        '_' => ['Rule'],
        '`' => ['FencedCode'],
        '|' => ['Table'],
        '~' => ['FencedCode']
    ];
    /**
     * @var array<string>
     */
    private array $unmarkedBlockTypes = ['Code'];

    /**
     * @param array<string> $lines
     */
    public function lines(array $lines): string
    {
        $blocks = [];
        $currentBlock = null;

        foreach ($lines as $line) {
            $line = $this->processLine($line);

            if ($this->isEmptyLine($line)) {
                $this->handleEmptyLine($currentBlock);
                continue;
            }

            $lineData = $this->parseLine($line);

            if (isset($currentBlock['continuable'])) {
                $this->handleContinuableBlock($currentBlock, $lineData);
            }

            $blockTypes = $this->getBlockTypes($lineData['text'][0]);

            if ($this->tryBlockTypes($blockTypes, $lineData, $currentBlock, $blocks)) {
                continue;
            }

            $this->handleNonContinuableBlock($currentBlock, $lineData, $blocks);
        }

        $this->finalizeCurrentBlock($currentBlock, $blocks);

        return $this->generateMarkup($blocks);
    }

    private function processLine(string $line): string
    {
        if (strpos($line, "\t") !== false) {
            $parts = explode("\t", $line);
            $line = array_shift($parts);
            foreach ($parts as $part) {
                $shortage = 4 - mb_strlen($line, 'utf-8') % 4;
                $line .= str_repeat(' ', $shortage) . $part;
            }
        }
        return $line;
    }

    private function isEmptyLine(string $line): bool
    {
        return chop($line) === '';
    }
    /**
     * @param array<string, mixed>|null $currentBlock
     */
    private function handleEmptyLine(?array &$currentBlock): void
    {
        if (isset($currentBlock)) {
            $currentBlock['interrupted'] = true;
        }
    }

    /**
     * @return array{body: string, indent: int, text: string}
     */
    private function parseLine(string $line): array
    {
        $indent = 0;
        while (isset($line[$indent]) && $line[$indent] === ' ') {
            $indent++;
        }
        $text = $indent > 0 ? substr($line, $indent) : $line;
        return ['body' => $line, 'indent' => $indent, 'text' => $text];
    }

    /**
     * @param array<string, mixed> $currentBlock
     */
    private function handleContinuableBlock(array &$currentBlock, array $lineData): void
    {
        $block = $this->{'block' . $currentBlock['type'] . 'Continue'}($lineData, $currentBlock);

        if ($this->isBlockCompletable($currentBlock['type'])) {
            $currentBlock = $this->{'block' . $currentBlock['type'] . 'Complete'}($currentBlock);
        }
    }

    /**
     * @return array<string>
     */
    private function getBlockTypes(string $marker): array
    {
        $blockTypes = $this->unmarkedBlockTypes;
        if (isset($this->blockTypes[$marker])) {
            $blockTypes = array_merge($blockTypes, $this->blockTypes[$marker]);
        }
        return $blockTypes;
    }

    /**
     * @param array<string> $blockTypes
     * @param array<string, mixed> $lineData
     * @param array<string, mixed>|null $currentBlock
     * @param array<string, mixed> $blocks
     */
    private function tryBlockTypes(array $blockTypes, array $lineData, ?array &$currentBlock, array &$blocks): bool
    {
        foreach ($blockTypes as $blockType) {
            $block = $this->{'block' . $blockType}($lineData, $currentBlock);
            if (isset($block)) {
                $block['type'] = $blockType;
                if (!isset($block['identified'])) {
                    $blocks[] = $currentBlock;
                    $block['identified'] = true;
                }
                if ($this->isBlockContinuable($blockType)) {
                    $block['continuable'] = true;
                }
                $currentBlock = $block;
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed>|null $currentBlock
     */
    private function handleNonContinuableBlock(?array &$currentBlock, array $lineData, array &$blocks): void
    {
        if (isset($currentBlock) && !isset($currentBlock['type']) && !isset($currentBlock['interrupted'])) {
            $currentBlock['element']['text'] .= "\n" . $lineData['text'];
        } else {
            $blocks[] = $currentBlock;
            $currentBlock = $this->paragraph($lineData);
            $currentBlock['identified'] = true;
        }
    }

    /**
     * @param array<string, mixed>|null $currentBlock
     */
    private function finalizeCurrentBlock(?array &$currentBlock, array &$blocks): void
    {
        if (isset($currentBlock['continuable']) && $this->isBlockCompletable($currentBlock['type'])) {
            $currentBlock = $this->{'block' . $currentBlock['type'] . 'Complete'}($currentBlock);
        }
        $blocks[] = $currentBlock;
        unset($blocks[0]);
    }

    /**
     * @param array<string, mixed> $blocks
     */
    private function generateMarkup(array $blocks): string
    {
        $markup = '';
        foreach ($blocks as $block) {
            if (isset($block['hidden'])) {
                continue;
            }
            $markup .= "\n" . (isset($block['markup']) ? $block['markup'] : $this->element($block['element']));
        }
        return $markup . "\n";
    }

    /**
     * @param string $type
     */
    protected function isBlockContinuable(string $type): bool
    {
        return method_exists($this, 'block' . $type . 'Continue');
    }

    /**
     * @param string $type
     */
    protected function isBlockCompletable(string $type): bool
    {
        return method_exists($this, 'block' . $type . 'Complete');
    }

    protected function paragraph($Line)
    {
        $Block = array('element' => array('name' => 'p', 'text' => $Line['text'], 'handler' => 'line'));
        return $Block;
    }
}
