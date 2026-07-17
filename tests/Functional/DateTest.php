<?php

declare(strict_types=1);

use Indieinabox\Page;

beforeEach(function () {
    global $originaldaysofweek, $originalmonths, $intl;
});

it('formats localized dates correctly for english (en)', function () {
    $timestamp = 1609459200; // 2021-01-01 00:00:00 UTC -> 2020-12-31 21:00:00 -03:00

    // Array page representation
    $pageArray = [
        'date' => $timestamp,
        'lang' => 'en'
    ];
    $result = localizeddate($pageArray);

    expect($result['long'])->toBe('Thursday, December 31, 2020 at 09:00 PM')
        ->and($result['iso'])->toContain('2020-12-31T21:00:00-03:00');

    // Page object representation
    $pageObj = Page::fromArray($pageArray);
    $resultObj = localizeddate($pageObj);

    expect($resultObj['long'])->toBe('Thursday, December 31, 2020 at 09:00 PM')
        ->and($resultObj['iso'])->toContain('2020-12-31T21:00:00-03:00');
});

it('handles different epoch type representations correctly', function () {
    $timestampInt = 1609459200;
    $timestampFloat = 1609459200.0;
    $timestampStr = "1609459200";
    $dateTime = new DateTime('@1609459200');

    // Integer
    $resInt = localizeddate(['date' => $timestampInt, 'lang' => 'en']);
    // Float
    $resFloat = localizeddate(['date' => $timestampFloat, 'lang' => 'en']);
    // String
    $resStr = localizeddate(['date' => $timestampStr, 'lang' => 'en']);
    // DateTime
    $resDt = localizeddate(['date' => $dateTime, 'lang' => 'en']);

    expect($resInt['long'])->toBe('Thursday, December 31, 2020 at 09:00 PM')
        ->and($resFloat['long'])->toBe('Thursday, December 31, 2020 at 09:00 PM')
        ->and($resStr['long'])->toBe('Thursday, December 31, 2020 at 09:00 PM')
        ->and($resDt['long'])->toBe('Thursday, December 31, 2020 at 09:00 PM');
});
