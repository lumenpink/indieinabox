<?php

declare(strict_types=1);

use Indieinabox\Localization\Translator;

it('handles translation fallbacks and pluralization', function () {
    expect(Translator::translate('Hello', 'en'))->toBe('Hello')
        ->and(Translator::translatePlural('Item', 'Items', 1, 'en'))->toBe('Item')
        ->and(Translator::translatePlural('Item', 'Items', 3, 'en'))->toBe('Items')
        ->and(Translator::translateLowercase('HELLO', 'en'))->toBe('hello')
        ->and(Translator::translateSlugize('Hello World', 'en'))->toBe('hello-world');
});
