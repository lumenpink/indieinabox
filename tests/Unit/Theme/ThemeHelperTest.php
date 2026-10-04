<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Theme\ThemeHelper;

it('renders metadata html safely when tags or flowerbed contain arrays or empty items', function () {
    $page = Page::fromArray([
        'title' => 'Test Page',
        'slug' => 'test-page',
        'kind' => 'garden',
        'date' => '2026-10-04 12:00:00',
        'tags' => ['valid', [], ''],
        'flowerbed' => ['general', []],
    ]);
    $page->localizeddate = 'Sunday, October 4 of 2026, 12:00 UTC';

    $html = ThemeHelper::getMetadataHtml($page);

    expect($html)->toContain('#valid')
        ->and($html)->not->toContain('#Array');
});
