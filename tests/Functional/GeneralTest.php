<?php

declare(strict_types=1);

it('minifies HTML content by keeping comments and collapsing whitespace', function () {
    $rawHtml = "<!-- This is a comment -->\n<html>\n  <body>\n    <h1>  Hello   World  </h1>\n  </body>\n</html>";
    $minified = minifyhtml($rawHtml);

    expect($minified)->toContain('<!-- This is a comment -->')
        ->and($minified)->toBe('<!-- This is a comment --><html><body><h1> Hello World </h1></body></html>');
});

it('beautifies HTML content by applying correct indentations', function () {
    $rawHtml = "<html><body><h1>Hello World</h1></body></html>";
    $beautified = beautifyhtml($rawHtml);

    expect($beautified)->toContain("<html>")
        ->and($beautified)->toContain("<body>")
        ->and($beautified)->toContain("  <h1>Hello World</h1>")
        ->and($beautified)->toContain("</body>")
        ->and($beautified)->toContain("</html>");
});

it('returns empty string for empty HTML input in minifier and beautifier', function () {
    expect(minifyhtml(''))->toBe('')
        ->and(beautifyhtml(''))->toBe('');
});
