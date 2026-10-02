<?php

declare(strict_types=1);

use Indieinabox\Markdown\ASTParser;
use Indieinabox\Markdown\DialogueSystem;
use Indieinabox\Markdown\HtmlRenderer;

it('renders character dialogue boxes with emotional avatars correctly (#73)', function () {
    $markup = '[dialog char="sh0rk" emote="happy"]Eu adoro nadar no mar![/dialog]';
    $html = DialogueSystem::process($markup);

    expect($html)->toContain('class="dialog-box"')
        ->and($html)->toContain('sh0rk (Blåhaj)')
        ->and($html)->toContain('🦈✨')
        ->and($html)->toContain('Eu adoro nadar no mar!');

    // Test alternate [say:character:emote] syntax
    $markupSay = '[say:mara:thinking]O que será que vamos construir hoje?[/say]';
    $htmlSay = DialogueSystem::process($markupSay);

    expect($htmlSay)->toContain('class="dialog-box"')
        ->and($htmlSay)->toContain('Mara')
        ->and($htmlSay)->toContain('🦊💭')
        ->and($htmlSay)->toContain('O que será que vamos construir hoje?');
});

it('renders cowsay speech bubbles with cow and blahaj characters (#74)', function () {
    $markupCow = '[cowsay]IndieWeb rocks![/cowsay]';
    $htmlCow = DialogueSystem::process($markupCow);

    expect($htmlCow)->toContain('class="cowsay-bubble"')
        ->and($htmlCow)->toContain('IndieWeb rocks!')
        ->and($htmlCow)->toContain('(oo)\\_______');

    $markupBlahaj = '[cowsay char="blahaj"]Trans rights now![/cowsay]';
    $htmlBlahaj = DialogueSystem::process($markupBlahaj);

    expect($htmlBlahaj)->toContain('class="cowsay-bubble"')
        ->and($htmlBlahaj)->toContain('Trans rights now!')
        ->and($htmlBlahaj)->toContain('sh0rk');
});

it('renders Microsoft Comic Chat style multi-panel strips from declarative markup (#75)', function () {
    $markup = <<<EOT
[comic title="Conversa Casual"]
Alice (sorrindo): Olá, Bob!
Bob (pensativo): Será que isso funciona via marcação pura?
Alice (animada): Funciona sim, estilo Comic Chat!
[/comic]
EOT;

    $html = DialogueSystem::process($markup);

    expect($html)->toContain('class="comic-chat-strip"')
        ->and($html)->toContain('Conversa Casual')
        ->and($html)->toContain('class="comic-panel"')
        ->and($html)->toContain('Alice')
        ->and($html)->toContain('Bob')
        ->and($html)->toContain('Olá, Bob!')
        ->and($html)->toContain('Será que isso funciona via marcação pura?')
        ->and($html)->toContain('Funciona sim, estilo Comic Chat!');
});

it('integrates dialogues, cowsay, and comics seamlessly through ASTParser and HtmlRenderer', function () {
    $markdown = <<<EOT
# Post com Diálogos

[say:sh0rk:happy]Tudo funcionando perfeitamente![/say]

[cowsay char="blahaj"]Moo-shark![/cowsay]

[comic title="Mini Tirinha"]
Lumen (animada): Agora sim nosso jardim tem comics!
[/comic]
EOT;

    $parser = new ASTParser();
    $ast = $parser->parse($markdown);
    $renderer = new HtmlRenderer();
    $html = $renderer->render($ast);

    expect($html)->toContain('Post com Diálogos')
        ->and($html)->toContain('class="dialog-box"')
        ->and($html)->toContain('class="cowsay-bubble"')
        ->and($html)->toContain('class="comic-chat-strip"');
});
