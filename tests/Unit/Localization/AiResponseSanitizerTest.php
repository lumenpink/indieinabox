<?php

declare(strict_types=1);

use Indieinabox\Localization\Services\AiResponseSanitizer;

it('removes outer markdown code fence wrapping', function () {
    $raw = "```markdown\n# Hello World\nThis is my text.\n```";
    expect(AiResponseSanitizer::sanitize($raw))->toBe("# Hello World\nThis is my text.");

    $rawMd = "```md\n# Content\n```";
    expect(AiResponseSanitizer::sanitize($rawMd))->toBe("# Content");

    $rawPlain = "```\nPlain code block wrapper\n```";
    expect(AiResponseSanitizer::sanitize($rawPlain))->toBe("Plain code block wrapper");
});

it('removes English conversational preambles', function () {
    $raw1 = "Here is the translated article:\n# My Heading\nSome content.";
    expect(AiResponseSanitizer::sanitize($raw1))->toBe("# My Heading\nSome content.");

    $raw2 = "Sure, here's the translation:\nParagraph one.";
    expect(AiResponseSanitizer::sanitize($raw2))->toBe("Paragraph one.");

    $raw3 = "Translation to Portuguese:\nTexto traduzido.";
    expect(AiResponseSanitizer::sanitize($raw3))->toBe("Texto traduzido.");
});

it('removes Portuguese conversational preambles', function () {
    $raw1 = "Aqui está o texto traduzido:\n# Meu Título\nConteúdo.";
    expect(AiResponseSanitizer::sanitize($raw1))->toBe("# Meu Título\nConteúdo.");

    $raw2 = "Com certeza, segue a tradução:\nParágrafo do post.";
    expect(AiResponseSanitizer::sanitize($raw2))->toBe("Parágrafo do post.");

    $raw3 = "Tradução para português:\nTexto aqui.";
    expect(AiResponseSanitizer::sanitize($raw3))->toBe("Texto aqui.");
});

it('does not strip the word Tradução or Translation when part of normal text', function () {
    $raw1 = "Tradução memorizada em cache.";
    expect(AiResponseSanitizer::sanitize($raw1))->toBe("Tradução memorizada em cache.");

    $raw2 = "Translation is a complex process.";
    expect(AiResponseSanitizer::sanitize($raw2))->toBe("Translation is a complex process.");
});

it('removes conversational closings in English and Portuguese', function () {
    $raw1 = "# Title\n\nContent here.\n\nHope this helps!";
    expect(AiResponseSanitizer::sanitize($raw1))->toBe("# Title\n\nContent here.");

    $raw2 = "Texto final.\n\nEspero que ajude! Diga o que achou.";
    expect(AiResponseSanitizer::sanitize($raw2))->toBe("Texto final.");

    $raw3 = "Artigo pronto.\n\nQualquer dúvida estou à disposição!";
    expect(AiResponseSanitizer::sanitize($raw3))->toBe("Artigo pronto.");

    $raw4 = "Paragraph.\n\nLet me know if you need any adjustments.";
    expect(AiResponseSanitizer::sanitize($raw4))->toBe("Paragraph.");
});
