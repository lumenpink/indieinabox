<?php

declare(strict_types=1);

use Indieinabox\Entry\Entry;
use Indieinabox\Feeds\Generators\TwtxtFeedGenerator;
use Indieinabox\Helper;
use Indieinabox\Site;
use Indieinabox\Site\Paths;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_twtxt_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $paths = new Paths($this->tempDir);
    $this->site = new Site(null, $paths);
    $this->site->metadata->fqdn = 'https://example.com';
    $this->site->twtxt->nick = 'tester';
    $this->site->twtxt->description = 'A twtxt test user';
    $this->site->twtxt->avatar = 'https://example.com/avatar.png';
    $this->generator = new TwtxtFeedGenerator();
});

afterEach(function () {
    Helper::recursiveRmdir($this->tempDir);
});

it('returns correct filename', function () {
    expect($this->generator->getFilename())->toBe('twtxt.txt');
});

it('generates valid twtxt feed from Entry objects in chronological order', function () {
    $entries = [
        new Entry([
            'slug' => 'newer-note',
            'rawContent' => 'Meu post mais recente #php',
            'publishedAt' => new \DateTimeImmutable('2026-09-12 12:00:00'),
            'kind' => 'note',
        ]),
        new Entry([
            'slug' => 'older-article',
            'title' => 'Artigo Antigo',
            'rawContent' => 'Um resumo bem bacana do artigo antigo.',
            'publishedAt' => new \DateTimeImmutable('2026-09-10 10:00:00'),
            'kind' => 'article',
        ]),
        new Entry([
            'slug' => 'draft-note',
            'rawContent' => 'Rascunho não entra',
            'isDraft' => true,
        ]),
    ];

    $outFile = $this->tempDir . '/public_html/twtxt.txt';
    $this->generator->generate($entries, $outFile, $this->site);

    expect(file_exists($outFile))->toBeTrue();
    $content = file_get_contents($outFile);

    expect($content)->toContain('# nick = tester');
    expect($content)->toContain('# description = A twtxt test user');
    expect($content)->toContain('# avatar = https://example.com/avatar.png');

    // Chronological order: older-article (2026-09-10) should come before newer-note (2026-09-12)
    $posOlder = strpos($content, 'Artigo Antigo');
    $posNewer = strpos($content, 'Meu post mais recente #php');

    expect($posOlder)->not()->toBeFalse();
    expect($posNewer)->not()->toBeFalse();
    expect($posOlder)->toBeLessThan($posNewer);
    expect($content)->not()->toContain('Rascunho não entra');
});
