<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Core\Database;
use Indieinabox\Microsub\ExtendedEntry;
use Indieinabox\Repositories\SqliteMicrosubRepository;
use Indieinabox\Services\FetchFeedsService;
use Indieinabox\Services\MicrosubFilterService;
use Indieinabox\Services\MicrosubService;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/microsub_filter_integration_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);

    Database::disconnect();
    $dbPath = $this->tempDir . '/test.sqlite';
    Database::$dataDir = $this->tempDir . '/data';
    Database::connect($dbPath);
    $db = Database::getDb();
    $schema = file_get_contents(__DIR__ . '/../../../database.sql');
    $db->exec($schema);

    $this->repo = new SqliteMicrosubRepository($db);
    $this->filterService = new MicrosubFilterService($this->repo);
    $this->fetcher = new FetchFeedsService($db, null, null, $this->filterService);
    $this->microsub = new MicrosubService($db, $this->fetcher, null, $this->repo, null, null, $this->filterService);
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    FileUtils::recursiveRmdir($this->tempDir);
});

describe('MicrosubFilter integration in FetchFeedsService', function () {
    it('drops entries matching a drop filter during saveEntry', function () {
        $this->filterService->addFilter('keyword', 'scam-promo', 'drop', '*');

        $entry = new ExtendedEntry();
        $entry->uid = 'entry-dropped-1';
        $entry->url = 'https://remote.example/post/1';
        $entry->content['text'] = 'Check out this scam-promo right now!';

        $saved = $this->fetcher->saveEntry($entry, 'inbox');
        expect($saved)->toBeFalse();
        expect($this->fetcher->itemExists('entry-dropped-1', 'inbox'))->toBeFalse();
    });

    it('auto-archives entries matching an auto_archive filter during saveEntry', function () {
        $this->filterService->addFilter('domain', 'news-feed.org', 'auto_archive', '*');

        $entry = new ExtendedEntry();
        $entry->uid = 'entry-archived-1';
        $entry->url = 'https://news-feed.org/headline/123';
        $entry->content['text'] = 'Daily mundane digest news.';

        $saved = $this->fetcher->saveEntry($entry, 'inbox');
        expect($saved)->toBeTrue();
        expect($this->fetcher->itemExists('entry-archived-1', 'inbox'))->toBeTrue();

        $timeline = $this->microsub->getTimeline('inbox');
        expect($timeline['items'])->toHaveCount(1);
        expect($timeline['items'][0]['_id'])->toBe('entry-archived-1');
        expect($timeline['items'][0]['_is_read'])->toBeTrue();
    });
});

describe('MicrosubFilter integration in MicrosubService getTimeline', function () {
    it('mutes entries matching a mute filter from timeline results', function () {
        // Save two entries normally
        $entry1 = new ExtendedEntry();
        $entry1->uid = 'post-1-clean';
        $entry1->url = 'https://alice.example/1';
        $entry1->author = ['name' => 'Alice'];
        $entry1->content['html'] = '<p>Lovely sunny day in the garden.</p>';
        $this->fetcher->saveEntry($entry1, 'inbox');

        $entry2 = new ExtendedEntry();
        $entry2->uid = 'post-2-noisy';
        $entry2->url = 'https://bob.example/2';
        $entry2->author = ['name' => 'Bob Spammer'];
        $entry2->content['html'] = '<p>Crypto pump to the moon!</p>';
        $this->fetcher->saveEntry($entry2, 'inbox');

        // Without filters, both appear
        $initialTimeline = $this->microsub->getTimeline('inbox');
        expect($initialTimeline['items'])->toHaveCount(2);

        // Add filter to mute by author
        $this->filterService->addFilter('author', 'Bob Spammer', 'mute', 'inbox');

        // Now getTimeline should exclude post-2-noisy
        $filteredTimeline = $this->microsub->getTimeline('inbox');
        expect($filteredTimeline['items'])->toHaveCount(1);
        expect($filteredTimeline['items'][0]['_id'])->toBe('post-1-clean');
    });

    it('auto-archives unread entries matching an auto_archive filter on timeline fetch', function () {
        $entry = new ExtendedEntry();
        $entry->uid = 'post-auto-read';
        $entry->url = 'https://feed.example/item';
        $entry->content['html'] = '<p>Automated newsletter digest notification.</p>';
        $this->fetcher->saveEntry($entry, 'inbox');

        // Add filter to auto-archive by keyword
        $this->filterService->addFilter('keyword', 'newsletter digest', 'auto_archive', '*');

        $timeline = $this->microsub->getTimeline('inbox');
        expect($timeline['items'])->toHaveCount(1);
        expect($timeline['items'][0]['_is_read'])->toBeTrue();
    });
});
