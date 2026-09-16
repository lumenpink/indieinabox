<?php

declare(strict_types=1);

use Indieinabox\Entry\Entry;
use Indieinabox\Feeds\Generators\AtomFeedGenerator;
use Indieinabox\Support\FileUtils;
use Indieinabox\Site\Site;
use Indieinabox\Site\Paths;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_atom_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $paths = new Paths($this->tempDir);
    $this->site = new Site(null, $paths);
    $this->site->metadata->sitename = 'Atom Test Blog';
    $this->site->metadata->description = 'An Atom feed test';
    $this->site->metadata->author = 'Alice';
    $this->site->metadata->fqdn = 'https://example.com';
    $this->generator = new AtomFeedGenerator();
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

it('returns correct filename', function () {
    expect($this->generator->getFilename())->toBe('atom.xml');
});

it('generates valid Atom 1.0 feed from Entry objects', function () {
    $entries = [
        new Entry([
            'slug' => 'entry-1',
            'title' => 'Primeira Entrada',
            'summary' => 'Resumo da primeira entrada',
            'content' => '<p>Conteúdo completo</p>',
            'publishedAt' => new \DateTimeImmutable('2026-09-11 12:00:00'),
            'kind' => 'article',
        ]),
        new Entry([
            'slug' => 'entry-2',
            'title' => 'Segunda Entrada com Autor Customizado',
            'content' => '<p>Texto da segunda</p>',
            'publishedAt' => new \DateTimeImmutable('2026-09-12 18:00:00'),
            'author' => ['name' => 'Bob', 'url' => 'https://bob.com'],
            'kind' => 'note',
        ]),
        new Entry([
            'slug' => 'draft-entry',
            'title' => 'Rascunho Secreto',
            'content' => '<p>Segredo</p>',
            'isDraft' => true,
        ]),
    ];

    $outFile = $this->tempDir . '/public_html/atom.xml';
    $this->generator->generate($entries, $outFile, $this->site);

    expect(file_exists($outFile))->toBeTrue();
    $xml = file_get_contents($outFile);

    expect($xml)->toContain('<feed xmlns="http://www.w3.org/2005/Atom">');
    expect($xml)->toContain('<title>Atom Test Blog</title>');
    expect($xml)->toContain('<title>Segunda Entrada com Autor Customizado</title>');
    expect($xml)->toContain('<name>Bob</name>');
    expect($xml)->toContain('<uri>https://bob.com</uri>');
    expect($xml)->toContain('<summary>Resumo da primeira entrada</summary>');
    expect($xml)->not()->toContain('Rascunho Secreto');
});
