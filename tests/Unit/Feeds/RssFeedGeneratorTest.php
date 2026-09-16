<?php

declare(strict_types=1);

use Indieinabox\Entry\Entry;
use Indieinabox\Feeds\Generators\RssFeedGenerator;
use Indieinabox\Support\FileUtils;
use Indieinabox\Site\Site;
use Indieinabox\Site\Paths;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_rss_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $paths = new Paths($this->tempDir);
    $this->site = new Site(null, $paths);
    $this->site->metadata->sitename = 'Test Blog';
    $this->site->metadata->description = 'A test blog';
    $this->site->metadata->fqdn = 'https://example.com';
    $this->generator = new RssFeedGenerator();
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

it('returns correct filename', function () {
    expect($this->generator->getFilename())->toBe('rss.xml');
});

it('generates valid RSS 2.0 feed from Entry objects', function () {
    $entries = [
        new Entry([
            'slug' => 'post-1',
            'title' => 'Primeiro Post',
            'content' => '<p>Olá Mundo</p>',
            'publishedAt' => new \DateTimeImmutable('2026-09-10 10:00:00'),
            'kind' => 'article',
        ]),
        new Entry([
            'slug' => 'post-2',
            'title' => 'Segundo Post',
            'content' => '<p>Mais conteúdo</p>',
            'publishedAt' => new \DateTimeImmutable('2026-09-12 15:00:00'),
            'kind' => 'article',
            'poll' => [
                'options' => [
                    ['title' => 'Sim', 'votes' => 3],
                    ['title' => 'Não', 'votes' => 1],
                ],
            ],
            'attachments' => [
                ['type' => 'audio', 'url' => 'https://example.com/audio.mp3', 'mime' => 'audio/mpeg', 'size' => 12345],
            ],
        ]),
        new Entry([
            'slug' => 'draft-post',
            'title' => 'Rascunho',
            'content' => '<p>Rascunho não deve aparecer</p>',
            'isDraft' => true,
        ]),
    ];

    $outFile = $this->tempDir . '/public_html/rss.xml';
    $this->generator->generate($entries, $outFile, $this->site);

    expect(file_exists($outFile))->toBeTrue();
    $xmlContent = file_get_contents($outFile);

    expect($xmlContent)->toContain('<rss version="2.0"');
    expect($xmlContent)->toContain('<title>Test Blog</title>');
    expect($xmlContent)->toContain('<title>Segundo Post</title>');
    expect($xmlContent)->toContain('<title>Primeiro Post</title>');
    expect($xmlContent)->not()->toContain('Rascunho');
    expect($xmlContent)->toContain('poll-fallback');
    expect($xmlContent)->toContain('<enclosure url="https://example.com/audio.mp3" type="audio/mpeg" length="12345"');
});
