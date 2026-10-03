<?php

declare(strict_types=1);

use Indieinabox\Localization\Services\PositionalLinkMasker;

it('masks code blocks, inline code, images, and links', function () {
    $markdown = <<<MD
# Header

Here is an [internal link](/notes/my-note/) and an external [Google](https://google.com).
Check this image: ![My Photo](/media/photo.jpg "A nice photo").

And some inline `const x = 10;` with a code block:

```php
function hello() {
    return 'world';
}
```

Final text.
MD;

    $result = PositionalLinkMasker::mask($markdown);
    $masked = $result['maskedContent'];
    $tokens = $result['tokens'];

    expect($masked)->not->toContain('function hello()');
    expect($masked)->not->toContain('const x = 10;');
    expect($masked)->not->toContain('/notes/my-note/');
    expect($masked)->not->toContain('https://google.com');
    expect($masked)->not->toContain('/media/photo.jpg');

    expect($tokens)->toHaveKeys([
        '__CODE_BLOCK_0__',
        '__INLINE_CODE_1__',
        '__IMG_TARGET_2__',
        '__LNK_TARGET_3__',
        '__LNK_TARGET_4__'
    ]);

    // Test exact restoration
    $restored = PositionalLinkMasker::restore($masked, $tokens);
    expect($restored)->toBe($markdown);
});

it('rewrites internal links via urlResolver callback during masking', function () {
    $markdown = 'Check out [my garden](/garden/tulips/) and [an article](/articles/my-post/).';

    $urlResolver = function (string $url): string {
        if ($url === '/garden/tulips/') {
            return '/pt/jardim/tulipas/';
        }
        if ($url === '/articles/my-post/') {
            return '/pt/artigos/meu-post/';
        }
        return $url;
    };

    $result = PositionalLinkMasker::mask($markdown, $urlResolver);
    $masked = $result['maskedContent'];
    $tokens = $result['tokens'];

    expect($masked)->toBe('Check out [my garden](__LNK_TARGET_0__) and [an article](__LNK_TARGET_1__).');

    // Simulate AI translation changing the link text but keeping the positional tokens intact
    $aiTranslated = 'Confira [meu jardim](__LNK_TARGET_0__) e [um artigo](__LNK_TARGET_1__).';
    $restored = PositionalLinkMasker::restore($aiTranslated, $tokens);

    expect($restored)->toBe('Confira [meu jardim](/pt/jardim/tulipas/) e [um artigo](/pt/artigos/meu-post/).');
});
