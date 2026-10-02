<?php

declare(strict_types=1);

namespace Indieinabox\Versioning;

/**
 * Class Differ
 *
 * Provides line-by-line diff computation using Longest Common Subsequence (LCS),
 * generating both standard Unified Diffs and MediaWiki-style 2-column comparative HTML tables.
 */
class Differ
{
    /**
     * Generates a standard Unified Diff string between two texts.
     *
     * @param string $oldText Original content.
     * @param string $newText Modified content.
     * @param string $oldLabel Label for original version.
     * @param string $newLabel Label for modified version.
     * @param int $contextLines Number of surrounding context lines to include in hunks.
     * @return string
     */
    public static function unifiedDiff(
        string $oldText,
        string $newText,
        string $oldLabel = 'old',
        string $newLabel = 'new',
        int $contextLines = 3
    ): string {
        $oldLines = self::splitLines($oldText);
        $newLines = self::splitLines($newText);

        $ops = self::diffOps($oldLines, $newLines);

        if (empty($ops)) {
            return '';
        }

        $hasChanges = false;
        foreach ($ops as $op) {
            if ($op['type'] !== 'equal') {
                $hasChanges = true;
                break;
            }
        }

        if (!$hasChanges) {
            return '';
        }

        $hunks = self::buildHunks($ops, $contextLines);

        $out = "--- {$oldLabel}\n";
        $out .= "+++ {$newLabel}\n";

        foreach ($hunks as $hunk) {
            $oldStart = $hunk['oldStart'];
            $oldCount = $hunk['oldCount'];
            $newStart = $hunk['newStart'];
            $newCount = $hunk['newCount'];

            $out .= "@@ -{$oldStart},{$oldCount} +{$newStart},{$newCount} @@\n";
            foreach ($hunk['lines'] as $lineItem) {
                $prefix = ' ';
                if ($lineItem['type'] === 'del') {
                    $prefix = '-';
                } elseif ($lineItem['type'] === 'add') {
                    $prefix = '+';
                }
                $out .= $prefix . $lineItem['line'] . "\n";
            }
        }

        return $out;
    }

    /**
     * Generates a MediaWiki-style 2-column comparative HTML table.
     *
     * @param string $oldText Original content.
     * @param string $newText Modified content.
     * @param string $oldTitle Title/version header for left column.
     * @param string $newTitle Title/version header for right column.
     * @return string HTML table.
     */
    public static function mediawikiDiffHtml(
        string $oldText,
        string $newText,
        string $oldTitle = 'Versão anterior',
        string $newTitle = 'Versão mais recente'
    ): string {
        $oldLines = self::splitLines($oldText);
        $newLines = self::splitLines($newText);

        $ops = self::diffOps($oldLines, $newLines);

        $rowsHtml = '';
        $i = 0;
        $count = count($ops);

        while ($i < $count) {
            $op = $ops[$i];

            if ($op['type'] === 'equal') {
                $escaped = htmlspecialchars($op['line'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $rowsHtml .= '<tr class="diff-row-context">';
                $rowsHtml .= '<td class="diff-marker"> </td><td class="diff-context"><code>' . $escaped . '</code></td>';
                $rowsHtml .= '<td class="diff-marker"> </td><td class="diff-context"><code>' . $escaped . '</code></td>';
                $rowsHtml .= '</tr>' . "\n";
                $i++;
            } else {
                // Collect adjacent deletions and additions to pair them side by side
                $dels = [];
                $adds = [];

                while ($i < $count && $ops[$i]['type'] !== 'equal') {
                    if ($ops[$i]['type'] === 'del') {
                        $dels[] = $ops[$i]['line'];
                    } elseif ($ops[$i]['type'] === 'add') {
                        $adds[] = $ops[$i]['line'];
                    }
                    $i++;
                }

                $max = max(count($dels), count($adds));
                for ($k = 0; $k < $max; $k++) {
                    $rowsHtml .= '<tr class="diff-row-change">';

                    if (isset($dels[$k])) {
                        $delEscaped = htmlspecialchars($dels[$k], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $rowsHtml .= '<td class="diff-marker diff-marker-del">−</td>';
                        $rowsHtml .= '<td class="diff-deletedline"><del>' . $delEscaped . '</del></td>';
                    } else {
                        $rowsHtml .= '<td class="diff-marker"> </td><td class="diff-empty"></td>';
                    }

                    if (isset($adds[$k])) {
                        $addEscaped = htmlspecialchars($adds[$k], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $rowsHtml .= '<td class="diff-marker diff-marker-add">+</td>';
                        $rowsHtml .= '<td class="diff-addedline"><ins>' . $addEscaped . '</ins></td>';
                    } else {
                        $rowsHtml .= '<td class="diff-marker"> </td><td class="diff-empty"></td>';
                    }

                    $rowsHtml .= '</tr>' . "\n";
                }
            }
        }

        $html = '<div class="diff-container table-responsive">' . "\n";
        $html .= '<table class="diff wiki-diff" role="grid">' . "\n";
        $html .= '<colgroup><col style="width: 2%;"><col style="width: 48%;"><col style="width: 2%;"><col style="width: 48%;"></colgroup>' . "\n";
        $html .= '<thead>' . "\n";
        $html .= '<tr>';
        $html .= '<th colspan="2" class="diff-otitle"><strong>' . htmlspecialchars($oldTitle) . '</strong></th>';
        $html .= '<th colspan="2" class="diff-ntitle"><strong>' . htmlspecialchars($newTitle) . '</strong></th>';
        $html .= '</tr>' . "\n";
        $html .= '</thead>' . "\n";
        $html .= '<tbody>' . "\n";
        $html .= $rowsHtml;
        $html .= '</tbody>' . "\n";
        $html .= '</table>' . "\n";
        $html .= '</div>' . "\n";

        return $html;
    }

    /**
     * Splits text into normalized lines.
     *
     * @param string $text
     * @return string[]
     */
    public static function splitLines(string $text): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        if ($normalized === '') {
            return [];
        }
        return explode("\n", $normalized);
    }

    /**
     * Computes diff operations using LCS algorithm.
     *
     * @param string[] $a
     * @param string[] $b
     * @return array<int, array{type: 'equal'|'del'|'add', line: string}>
     */
    public static function diffOps(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);

        // Fast path: identical arrays
        if ($a === $b) {
            $ops = [];
            foreach ($a as $line) {
                $ops[] = ['type' => 'equal', 'line' => $line];
            }
            return $ops;
        }

        // Fast path: $a empty
        if ($m === 0) {
            $ops = [];
            foreach ($b as $line) {
                $ops[] = ['type' => 'add', 'line' => $line];
            }
            return $ops;
        }

        // Fast path: $b empty
        if ($n === 0) {
            $ops = [];
            foreach ($a as $line) {
                $ops[] = ['type' => 'del', 'line' => $line];
            }
            return $ops;
        }

        // Compute LCS matrix
        $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
        for ($i = 0; $i < $m; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($a[$i] === $b[$j]) {
                    $lcs[$i + 1][$j + 1] = $lcs[$i][$j] + 1;
                } else {
                    $lcs[$i + 1][$j + 1] = max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
                }
            }
        }

        // Backtrack to build diff ops
        $ops = [];
        $i = $m;
        $j = $n;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i - 1] === $b[$j - 1]) {
                $ops[] = ['type' => 'equal', 'line' => $a[$i - 1]];
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                $ops[] = ['type' => 'add', 'line' => $b[$j - 1]];
                $j--;
            } elseif ($i > 0 && ($j === 0 || $lcs[$i][$j - 1] < $lcs[$i - 1][$j])) {
                $ops[] = ['type' => 'del', 'line' => $a[$i - 1]];
                $i--;
            }
        }

        return array_reverse($ops);
    }

    /**
     * Groups diff operations into hunks with context lines.
     *
     * @param array<int, array{type: 'equal'|'del'|'add', line: string}> $ops
     * @param int $context
     * @return array<int, array{oldStart: int, oldCount: int, newStart: int, newCount: int, lines: array<int, array{type: 'equal'|'del'|'add', line: string}>}>
     */
    private static function buildHunks(array $ops, int $context): array
    {
        $hunks = [];
        $currentHunk = null;

        $oldLineNo = 1;
        $newLineNo = 1;

        $n = count($ops);
        for ($idx = 0; $idx < $n; $idx++) {
            $op = $ops[$idx];

            if ($op['type'] !== 'equal') {
                if ($currentHunk === null) {
                    $currentHunk = [
                        'oldStart' => max(1, $oldLineNo - $context),
                        'oldCount' => 0,
                        'newStart' => max(1, $newLineNo - $context),
                        'newCount' => 0,
                        'lines' => [],
                    ];

                    // Backfill context lines
                    $backIdx = max(0, $idx - $context);
                    for ($k = $backIdx; $k < $idx; $k++) {
                        $currentHunk['lines'][] = $ops[$k];
                        $currentHunk['oldCount']++;
                        $currentHunk['newCount']++;
                    }
                }

                $currentHunk['lines'][] = $op;
                if ($op['type'] === 'del') {
                    $currentHunk['oldCount']++;
                } else {
                    $currentHunk['newCount']++;
                }
            } else {
                if ($currentHunk !== null) {
                    $currentHunk['lines'][] = $op;
                    $currentHunk['oldCount']++;
                    $currentHunk['newCount']++;

                    // Count upcoming changes within 2 * context
                    $upcomingChanges = 0;
                    for ($ahead = 1; $ahead <= $context * 2 && ($idx + $ahead) < $n; $ahead++) {
                        if ($ops[$idx + $ahead]['type'] !== 'equal') {
                            $upcomingChanges++;
                            break;
                        }
                    }

                    if ($upcomingChanges === 0) {
                        $hunks[] = $currentHunk;
                        $currentHunk = null;
                    }
                }
            }

            if ($op['type'] === 'equal' || $op['type'] === 'del') {
                $oldLineNo++;
            }
            if ($op['type'] === 'equal' || $op['type'] === 'add') {
                $newLineNo++;
            }
        }

        if ($currentHunk !== null) {
            $hunks[] = $currentHunk;
        }

        return $hunks;
    }
}
