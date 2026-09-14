<?php

declare(strict_types=1);

use Indieinabox\Support\TextParser;

it('retrieves nested array keys with arrayGet', function () {
    $array = ['title' => 'My Title', 'status' => 'draft'];

    expect(TextParser::arrayGet($array, 'title', 'Default'))->toBe('My Title')
        ->and(TextParser::arrayGet($array, 'missing', 'Default'))->toBe('Default')
        ->and(TextParser::arrayGet($array, 'missing'))->toBeNull();
});

it('extracts hashtags from text', function () {
    $text = "Hello world! This is a #test of the #Hashtag extraction #system. Also checking #unicodeça, #123 (should be ignored), and end of line #tag";
    $tags = TextParser::extractHashtags($text);

    expect($tags)->toHaveCount(5)
        ->and($tags)->toContain('test')
        ->and($tags)->toContain('hashtag')
        ->and($tags)->toContain('system')
        ->and($tags)->toContain('unicodeça')
        ->and($tags)->toContain('tag')
        ->and($tags)->not->toContain('123');
});

it('correctly unaccents and transliterates unicode strings', function () {
    expect(TextParser::unaccent('Olá mundo! Ação e emoção.'))->toBe('Ola mundo! Acao e emocao.')
        ->and(TextParser::utf8ToAscii('Hello World'))->toBe('Hello World')
        ->and(TextParser::utf8ToAscii('Café', '?'))->toBe('Caf?');
});

it('generates clean url slugs with slugize', function () {
    expect(TextParser::slugize('Hello World! 2026'))->toBe('hello-world-2026')
        ->and(TextParser::slugize('Crème Brûlée & Café'))->toBe('creme-brulee--cafe')
        ->and(TextParser::slugize('Multiple---Dashes'))->toBe('multiple---dashes');
});
