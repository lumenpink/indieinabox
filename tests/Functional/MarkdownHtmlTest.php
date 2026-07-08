<?php
declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Indieinabox\Markdown\ASTParser;
use Indieinabox\Markdown\HtmlRenderer;
use Indieinabox\Markdown\ContentProcessor;

it('parses block html and inline html without escaping them', function () {
    $markdown = <<<MD
# My Video

Here is a video:

<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen></iframe>

And some <span style="color:red;">inline html</span>!
MD;

    $parser = new ASTParser();
    $ast = $parser->parse($markdown);

    $renderer = new HtmlRenderer();
    $html = $renderer->render($ast);

    expect($html)->toContain('<iframe width="560"');
    expect($html)->toContain('<span style="color:red;">inline html</span>');
    expect($html)->not->toContain('&lt;iframe');
    expect($html)->not->toContain('&lt;span');
});
