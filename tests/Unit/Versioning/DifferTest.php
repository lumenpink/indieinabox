<?php

declare(strict_types=1);

use Indieinabox\Versioning\Differ;

it('generates unified diff between two texts', function () {
    $old = "Linha 1\nLinha 2 original\nLinha 3\n";
    $new = "Linha 1\nLinha 2 modificada\nLinha 3\nLinha 4 nova\n";

    $diff = Differ::unifiedDiff($old, $new, 'v1', 'v2');

    expect($diff)->toContain('--- v1');
    expect($diff)->toContain('+++ v2');
    expect($diff)->toContain('-Linha 2 original');
    expect($diff)->toContain('+Linha 2 modificada');
    expect($diff)->toContain('+Linha 4 nova');
});

it('returns empty string when texts are identical', function () {
    $text = "Linha A\nLinha B\n";
    $diff = Differ::unifiedDiff($text, $text);
    expect($diff)->toBe('');
});

it('generates MediaWiki 2-column comparative HTML table', function () {
    $old = "Primeira linha\nLinha apagada\nTerceira linha";
    $new = "Primeira linha\nLinha inserida\nTerceira linha";

    $html = Differ::mediawikiDiffHtml($old, $new, 'v1.20261002.1', 'v2.20261002.2');

    expect($html)->toContain('<table class="diff wiki-diff" role="grid">');
    expect($html)->toContain('v1.20261002.1');
    expect($html)->toContain('v2.20261002.2');
    expect($html)->toContain('diff-deletedline');
    expect($html)->toContain('<del>Linha apagada</del>');
    expect($html)->toContain('diff-addedline');
    expect($html)->toContain('<ins>Linha inserida</ins>');
    expect($html)->toContain('diff-context');
    expect($html)->toContain('Primeira linha');
});
