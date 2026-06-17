<?php

declare(strict_types=1);

use Indieinabox\Markdown\ASTParser;
use Indieinabox\Markdown\HtmlRenderer;
use Indieinabox\Markdown\HeadingNode;
use Indieinabox\Markdown\ParagraphNode;
use Indieinabox\Markdown\ListNode;
use Indieinabox\Markdown\ListItemNode;
use Indieinabox\Markdown\TextNode;
use Indieinabox\Markdown\StrongNode;
use Indieinabox\Markdown\EmphasisNode;
use Indieinabox\Markdown\CodeInlineNode;
use Indieinabox\Markdown\WikilinkNode;

it('parses headings correctly and extracts their levels', function () {
    $markdown = "# Level 1\n## Level 2\n###### Level 6";
    $parser = new ASTParser();
    $ast = $parser->parse($markdown);

    expect($ast->children)->toHaveCount(3);
    
    $h1 = $ast->children[0];
    expect($h1)->toBeInstanceOf(HeadingNode::class)
        ->and($h1->level)->toBe(1)
        ->and($h1->children[0])->toBeInstanceOf(TextNode::class)
        ->and($h1->children[0]->text)->toBe('Level 1');

    $h2 = $ast->children[1];
    expect($h2)->toBeInstanceOf(HeadingNode::class)
        ->and($h2->level)->toBe(2)
        ->and($h2->children[0]->text)->toBe('Level 2');

    $h6 = $ast->children[2];
    expect($h6)->toBeInstanceOf(HeadingNode::class)
        ->and($h6->level)->toBe(6)
        ->and($h6->children[0]->text)->toBe('Level 6');
});

it('renders headings to HTML correctly', function () {
    $markdown = "# Heading 1\n### Heading 3";
    $parser = new ASTParser();
    $ast = $parser->parse($markdown);
    
    $renderer = new HtmlRenderer();
    $html = $renderer->render($ast);
    
    expect($html)->toBe("<h1>Heading 1</h1>\n<h3>Heading 3</h3>\n");
});

it('parses standard paragraphs and handles multi-line paragraphs', function () {
    $markdown = "This is a single paragraph line.\nAnd this is appended to the same paragraph.\n\nThis is a new paragraph.";
    $parser = new ASTParser();
    $ast = $parser->parse($markdown);

    expect($ast->children)->toHaveCount(2);
    
    $p1 = $ast->children[0];
    expect($p1)->toBeInstanceOf(ParagraphNode::class)
        ->and($p1->children[0]->text)->toBe("This is a single paragraph line.\nAnd this is appended to the same paragraph.");

    $p2 = $ast->children[1];
    expect($p2)->toBeInstanceOf(ParagraphNode::class)
        ->and($p2->children[0]->text)->toBe("This is a new paragraph.");
});

it('parses lists and groups adjacent list items in a ListNode', function () {
    $markdown = "- Item 1\n* Item 2\n\n- Item 3";
    $parser = new ASTParser();
    $ast = $parser->parse($markdown);

    expect($ast->children)->toHaveCount(2);

    $list1 = $ast->children[0];
    expect($list1)->toBeInstanceOf(ListNode::class)
        ->and($list1->children)->toHaveCount(2);

    $item1 = $list1->children[0];
    expect($item1)->toBeInstanceOf(ListItemNode::class)
        ->and($item1->children[0]->text)->toBe('Item 1');

    $item2 = $list1->children[1];
    expect($item2)->toBeInstanceOf(ListItemNode::class)
        ->and($item2->children[0]->text)->toBe('Item 2');

    $list2 = $ast->children[1];
    expect($list2)->toBeInstanceOf(ListNode::class)
        ->and($list2->children)->toHaveCount(1);
});

it('renders lists to HTML correctly', function () {
    $markdown = "- Item 1\n- Item 2";
    $parser = new ASTParser();
    $ast = $parser->parse($markdown);
    
    $renderer = new HtmlRenderer();
    $html = $renderer->render($ast);
    
    expect($html)->toBe("<ul>\n  <li>Item 1</li>\n  <li>Item 2</li>\n</ul>\n");
});

it('parses inline elements including bold, italic, and inline code', function () {
    $markdown = "Demonstrating **bold**, *italic*, _emphasis_ and `code`.";
    $parser = new ASTParser();
    $ast = $parser->parse($markdown);
    
    $p = $ast->children[0];
    expect($p->children)->toHaveCount(9);

    expect($p->children[0])->toBeInstanceOf(TextNode::class)
        ->and($p->children[0]->text)->toBe('Demonstrating ');

    expect($p->children[1])->toBeInstanceOf(StrongNode::class)
        ->and($p->children[1]->children[0]->text)->toBe('bold');

    expect($p->children[3])->toBeInstanceOf(EmphasisNode::class)
        ->and($p->children[3]->children[0]->text)->toBe('italic');

    expect($p->children[5])->toBeInstanceOf(EmphasisNode::class)
        ->and($p->children[5]->children[0]->text)->toBe('emphasis');

    expect($p->children[7])->toBeInstanceOf(CodeInlineNode::class)
        ->and($p->children[7]->code)->toBe('code');
});

it('renders inline HTML correctly', function () {
    $markdown = "This is **bold** and *italic* and `code`.";
    $parser = new ASTParser();
    $ast = $parser->parse($markdown);
    
    $renderer = new HtmlRenderer();
    $html = $renderer->render($ast);
    
    expect($html)->toBe("<p>This is <strong>bold</strong> and <em>italic</em> and <code>code</code>.</p>\n");
});

it('parses Obsidian simple and aliased wikilinks', function () {
    $markdown = "Link to [[My Page]] and [[Another Page|Custom Label]].";
    $parser = new ASTParser();
    $ast = $parser->parse($markdown);
    
    $p = $ast->children[0];
    expect($p->children)->toHaveCount(5);

    $link1 = $p->children[1];
    expect($link1)->toBeInstanceOf(WikilinkNode::class)
        ->and($link1->target)->toBe('My Page')
        ->and($link1->label)->toBe('My Page');

    $link2 = $p->children[3];
    expect($link2)->toBeInstanceOf(WikilinkNode::class)
        ->and($link2->target)->toBe('Another Page')
        ->and($link2->label)->toBe('Custom Label');
});

it('renders Obsidian wikilinks to HTML correctly', function () {
    $markdown = "Check [[My Note]] and [[Target Note | Display Alias]].";
    $parser = new ASTParser();
    $ast = $parser->parse($markdown);
    
    $renderer = new HtmlRenderer();
    $html = $renderer->render($ast);
    
    expect($html)->toBe("<p>Check <a href=\"My Note\">My Note</a> and <a href=\"Target Note\">Display Alias</a>.</p>\n");
});

it('handles recursive nested inline nodes correctly', function () {
    $markdown = "This is **bold containing *italic* format**.";
    $parser = new ASTParser();
    $ast = $parser->parse($markdown);
    
    $p = $ast->children[0];
    expect($p->children)->toHaveCount(3);
    
    $strong = $p->children[1];
    expect($strong)->toBeInstanceOf(StrongNode::class)
        ->and($strong->children)->toHaveCount(3);
        
    expect($strong->children[0]->text)->toBe('bold containing ');
    expect($strong->children[1])->toBeInstanceOf(EmphasisNode::class)
        ->and($strong->children[1]->children[0]->text)->toBe('italic');
    expect($strong->children[2]->text)->toBe(' format');
});
