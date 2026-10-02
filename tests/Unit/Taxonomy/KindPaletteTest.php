<?php

declare(strict_types=1);

use Indieinabox\Taxonomy\TaxonomyService;
use Indieinabox\Theme\Whostyles;

it('provides high contrast and distinct default palettes for all post kinds compliant with WCAG AAA', function () {
    $kinds = [
        'article', 'note', 'garden', 'reply', 'like', 'repost',
        'bookmark', 'photo', 'video', 'checkin', 'rsvp', 'listen',
        'watch', 'read'
    ];

    $seenBgs = [];

    foreach ($kinds as $kind) {
        $palette = TaxonomyService::getDefaultPalette($kind);
        expect($palette)->toHaveKeys(['bg', 'fg'])
            ->and($palette['bg'])->toMatch('/^#[0-9A-Fa-f]{6}$/')
            ->and($palette['fg'])->toMatch('/^#[0-9A-Fa-f]{6}$/');

        // Verify contrast ratio >= 7.0:1 (WCAG AAA)
        $contrast = Whostyles::getContrast($palette['bg'], $palette['fg']);
        expect($contrast)->toBeGreaterThanOrEqual(7.0);

        // Ensure background colors are distinct and not duplicates
        expect($seenBgs)->not->toContain(strtolower($palette['bg']));
        $seenBgs[] = strtolower($palette['bg']);
    }
});
