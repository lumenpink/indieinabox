<?php

declare(strict_types=1);

use Indieinabox\Markdown\MarkdownValidator;

it('returns valid result for correctly formatted Markdown', function () {
    $markdown = <<<MARKDOWN
---
title: Valid Markdown Example
tags:
  - personal
  - draft
---
# Heading 1
This is a standard paragraph with **bold** and *italic* formatting and `code`.
Check out [[My Note]] or [[My Note | Display Alias]].
MARKDOWN;

    $validator = new MarkdownValidator($markdown);
    $result = $validator->validate();

    expect($result->isValid)->toBeTrue()
        ->and($result->errors)->toBeEmpty()
        ->and($result->warnings)->toBeEmpty();
});

it('detects unclosed and malformed front-matter blocks', function () {
    // 1. Unclosed front-matter
    $unclosed = "---\ntitle: Unclosed\ntags: [personal]";
    $validator1 = new MarkdownValidator($unclosed);
    $result1 = $validator1->validate();
    
    expect($result1->isValid)->toBeFalse()
        ->and($result1->errors)->toHaveCount(1)
        ->and($result1->errors[0]->line)->toBe(1)
        ->and($result1->errors[0]->message)->toContain('Front-matter block opened but not closed');

    // 2. Missing colon inside front-matter
    $missingColon = "---\ntitle: Missing Colon\ntags personal\n---";
    $validator2 = new MarkdownValidator($missingColon);
    $result2 = $validator2->validate();
    
    expect($result2->isValid)->toBeFalse()
        ->and($result2->errors)->toHaveCount(1)
        ->and($result2->errors[0]->line)->toBe(3)
        ->and($result2->errors[0]->message)->toContain('Invalid YAML key-value format');
});

it('warns when heading hash delimiter is glued to text', function () {
    $glued = "#Glued Heading 1\n##Glued Heading 2\n### Valid Heading";
    $validator = new MarkdownValidator($glued);
    $result = $validator->validate();

    expect($result->isValid)->toBeTrue()
        ->and($result->errors)->toBeEmpty()
        ->and($result->warnings)->toHaveCount(2)
        ->and($result->warnings[0]->line)->toBe(1)
        ->and($result->warnings[0]->message)->toContain('glued to text')
        ->and($result->warnings[1]->line)->toBe(2);
});

it('detects unclosed and malformed Obsidian wikilinks', function () {
    // 1. Unclosed wikilink
    $unclosed = "This has an [[unclosed wikilink\non the same line.";
    $validator1 = new MarkdownValidator($unclosed);
    $result1 = $validator1->validate();

    expect($result1->isValid)->toBeFalse()
        ->and($result1->errors)->toHaveCount(1)
        ->and($result1->errors[0]->line)->toBe(1)
        ->and($result1->errors[0]->message)->toContain('Unclosed wikilink');

    // 2. Empty wikilink
    $empty = "This has an empty wikilink [[]].";
    $validator2 = new MarkdownValidator($empty);
    $result2 = $validator2->validate();

    expect($result2->isValid)->toBeFalse()
        ->and($result2->errors)->toHaveCount(1)
        ->and($result2->errors[0]->line)->toBe(1)
        ->and($result2->errors[0]->message)->toContain('Empty or malformed wikilink');

    // 3. Malformed alias wikilink
    $malformed = "This has a malformed alias [[ | Alias]].";
    $validator3 = new MarkdownValidator($malformed);
    $result3 = $validator3->validate();

    expect($result3->isValid)->toBeFalse()
        ->and($result3->errors)->toHaveCount(1)
        ->and($result3->errors[0]->line)->toBe(1)
        ->and($result3->errors[0]->message)->toContain('Empty or malformed wikilink');
});

it('warns for unclosed inline code backticks', function () {
    $unclosed = "This has `unclosed code on a single line.";
    $validator = new MarkdownValidator($unclosed);
    $result = $validator->validate();

    expect($result->isValid)->toBeTrue()
        ->and($result->errors)->toBeEmpty()
        ->and($result->warnings)->toHaveCount(1)
        ->and($result->warnings[0]->line)->toBe(1)
        ->and($result->warnings[0]->message)->toContain('Unclosed inline code delimiter');
});

it('warns for unclosed bold and italic formatting delimiters at paragraph end', function () {
    // 1. Unclosed bold
    $unclosedBold = "This paragraph has a **bold format without closing.";
    $validator1 = new MarkdownValidator($unclosedBold);
    $result1 = $validator1->validate();

    expect($result1->isValid)->toBeTrue()
        ->and($result1->errors)->toBeEmpty()
        ->and($result1->warnings)->toHaveCount(1)
        ->and($result1->warnings[0]->line)->toBe(1)
        ->and($result1->warnings[0]->message)->toContain('Unclosed bold delimiter');

    // 2. Unclosed italic (asterisk)
    $unclosedItalic = "This paragraph has a *italic format without closing.";
    $validator2 = new MarkdownValidator($unclosedItalic);
    $result2 = $validator2->validate();

    expect($result2->isValid)->toBeTrue()
        ->and($result2->errors)->toBeEmpty()
        ->and($result2->warnings)->toHaveCount(1)
        ->and($result2->warnings[0]->line)->toBe(1)
        ->and($result2->warnings[0]->message)->toContain('Unclosed italic delimiter');
});
