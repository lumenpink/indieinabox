<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Support\DateFormatter;

it('formats relative time with timeAgo', function () {
    $now = time();
    expect(DateFormatter::timeAgo($now - 30))->toBe('30 seconds ago')
        ->and(DateFormatter::timeAgo($now - 300))->toBe('5 minutes ago')
        ->and(DateFormatter::timeAgo($now - 7200))->toBe('2 hours ago')
        ->and(DateFormatter::timeAgo($now - 172800))->toBe('2 days ago');
});

it('formats localized dates', function () {
    $timestamp = 1609459200; // 2021-01-01 00:00:00 UTC
    $page = ['date' => $timestamp, 'lang' => 'en'];

    $formatted = DateFormatter::localizeddate($page);

    expect($formatted['long'])->toBe('Thursday, December 31, 2020 at 09:00 PM')
        ->and($formatted['iso'])->toContain('2020-12-31T21:00:00-03:00');
});

it('sorts pages by date descending', function () {
    $page1 = new Page(null, null, null);
    $page1->date = new DateTime('2026-01-01');
    $page2 = new Page(null, null, null);
    $page2->date = new DateTime('2026-06-01');
    $page3 = new Page(null, null, null);
    $page3->date = new DateTime('2026-03-01');

    $sorted = DateFormatter::sortByDate([$page1, $page2, $page3]);

    expect($sorted[0]->date->format('Y-m-d'))->toBe('2026-06-01')
        ->and($sorted[1]->date->format('Y-m-d'))->toBe('2026-03-01')
        ->and($sorted[2]->date->format('Y-m-d'))->toBe('2026-01-01');
});
