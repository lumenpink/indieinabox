<?php

declare(strict_types=1);

use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Site\Site;
use Indieinabox\Specifications\Content\DateRangeSpecification;
use Indieinabox\Specifications\Content\KindSpecification;
use Indieinabox\Specifications\Content\LanguageSpecification;
use Indieinabox\Specifications\Content\SlugSpecification;
use Indieinabox\Specifications\Content\TagSpecification;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->tempDir = sys_get_temp_dir() . '/iiab_spec_' . uniqid();
    mkdir($this->tempDir);

    $this->site = new Site();
    $this->site->paths->contentDir = $this->tempDir;
    $this->contentRepo = new FileSystemContentRepository(null, $this->site);
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('KindSpecification matches single and multiple kinds case-insensitively', function () {
    $specArticle = new KindSpecification('article');
    $specMulti = new KindSpecification(['note', 'bookmark']);

    expect($specArticle->isSatisfiedBy(['kind' => 'article']))->toBeTrue();
    expect($specArticle->isSatisfiedBy(['kind' => 'Article']))->toBeTrue();
    expect($specArticle->isSatisfiedBy(['kind' => 'note']))->toBeFalse();

    expect($specMulti->isSatisfiedBy(['kind' => 'note']))->toBeTrue();
    expect($specMulti->isSatisfiedBy(['kind' => 'bookmark']))->toBeTrue();
    expect($specMulti->isSatisfiedBy(['kind' => 'article']))->toBeFalse();
});

test('TagSpecification matches any or all tags', function () {
    $candidate = [
        'frontmatter' => [
            'tags' => ['php', 'indieweb', 'architecture'],
        ],
    ];

    // Any match (default)
    $specAny = new TagSpecification(['php', 'python']);
    expect($specAny->isSatisfiedBy($candidate))->toBeTrue();

    $specNoMatch = new TagSpecification(['ruby', 'golang']);
    expect($specNoMatch->isSatisfiedBy($candidate))->toBeFalse();

    // All match
    $specAllTrue = new TagSpecification(['php', 'indieweb'], true);
    expect($specAllTrue->isSatisfiedBy($candidate))->toBeTrue();

    $specAllFalse = new TagSpecification(['php', 'python'], true);
    expect($specAllFalse->isSatisfiedBy($candidate))->toBeFalse();
});

test('DateRangeSpecification matches within date boundaries', function () {
    $candidatePast = ['date' => '2025-06-15 12:00:00'];
    $candidatePresent = ['date' => '2026-05-10 10:00:00'];
    $candidateFuture = ['date' => '2027-01-01 00:00:00'];

    $specBetween = new DateRangeSpecification('2026-01-01', '2026-12-31');
    expect($specBetween->isSatisfiedBy($candidatePast))->toBeFalse();
    expect($specBetween->isSatisfiedBy($candidatePresent))->toBeTrue();
    expect($specBetween->isSatisfiedBy($candidateFuture))->toBeFalse();

    $specAfter = new DateRangeSpecification('2026-01-01', null);
    expect($specAfter->isSatisfiedBy($candidatePast))->toBeFalse();
    expect($specAfter->isSatisfiedBy($candidatePresent))->toBeTrue();
    expect($specAfter->isSatisfiedBy($candidateFuture))->toBeTrue();

    $specBefore = new DateRangeSpecification(null, '2026-01-01');
    expect($specBefore->isSatisfiedBy($candidatePast))->toBeTrue();
    expect($specBefore->isSatisfiedBy($candidatePresent))->toBeFalse();

    // Candidate without date
    expect($specBetween->isSatisfiedBy([]))->toBeFalse();
});

test('LanguageSpecification and SlugSpecification match candidate properties', function () {
    $langSpec = new LanguageSpecification('pt');
    expect($langSpec->isSatisfiedBy(['lang' => 'pt']))->toBeTrue();
    expect($langSpec->isSatisfiedBy(['frontmatter' => ['lang' => 'pt']]))->toBeTrue();
    expect($langSpec->isSatisfiedBy(['lang' => 'en']))->toBeFalse();

    $slugSpec = new SlugSpecification('my-first-post');
    expect($slugSpec->isSatisfiedBy(['slug' => 'my-first-post']))->toBeTrue();
    expect($slugSpec->isSatisfiedBy(['slug' => '/my-first-post/']))->toBeTrue();
    expect($slugSpec->isSatisfiedBy(['slug' => 'other-post']))->toBeFalse();
});

test('Composite specifications support logical AND, OR, and NOT chaining', function () {
    $isArticle = new KindSpecification('article');
    $isPhp = new TagSpecification('php');
    $isNote = new KindSpecification('note');

    // AND
    $articleAndPhp = $isArticle->and($isPhp);
    expect($articleAndPhp->isSatisfiedBy([
        'kind' => 'article',
        'frontmatter' => ['tags' => ['php']],
    ]))->toBeTrue();

    expect($articleAndPhp->isSatisfiedBy([
        'kind' => 'article',
        'frontmatter' => ['tags' => ['rust']],
    ]))->toBeFalse();

    // OR
    $articleOrNote = $isArticle->or($isNote);
    expect($articleOrNote->isSatisfiedBy(['kind' => 'article']))->toBeTrue();
    expect($articleOrNote->isSatisfiedBy(['kind' => 'note']))->toBeTrue();
    expect($articleOrNote->isSatisfiedBy(['kind' => 'reply']))->toBeFalse();

    // NOT
    $notArticle = $isArticle->not();
    expect($notArticle->isSatisfiedBy(['kind' => 'note']))->toBeTrue();
    expect($notArticle->isSatisfiedBy(['kind' => 'article']))->toBeFalse();

    // Complex composition: (Article AND Php) OR Note
    $complex = $articleAndPhp->or($isNote);
    expect($complex->isSatisfiedBy(['kind' => 'note']))->toBeTrue();
    expect($complex->isSatisfiedBy(['kind' => 'article', 'frontmatter' => ['tags' => ['php']]]))->toBeTrue();
    expect($complex->isSatisfiedBy(['kind' => 'article', 'frontmatter' => ['tags' => ['ruby']]]))->toBeFalse();
});

test('ContentRepository queries files against specifications', function () {
    /** @var \Tests\TestCase $this */
    // Seed test posts
    $this->contentRepo->save(
        'article',
        'post-one',
        'Body of article one',
        ['title' => 'Article One', 'date' => '2026-03-01 10:00:00', 'tags' => ['php', 'web']]
    );
    $this->contentRepo->save(
        'article',
        'post-two',
        'Body of article two',
        ['title' => 'Article Two', 'date' => '2026-04-01 10:00:00', 'tags' => ['indieweb']]
    );
    $this->contentRepo->save(
        'note',
        'note-one',
        'Short quick note',
        ['date' => '2026-04-15 12:00:00', 'tags' => ['php']]
    );

    // Query articles only
    $articles = $this->contentRepo->query(new KindSpecification('article'));
    expect($articles)->toHaveCount(2);

    // Query PHP articles only (Article AND Php)
    $phpArticles = $this->contentRepo->query(
        (new KindSpecification('article'))->and(new TagSpecification('php'))
    );
    expect($phpArticles)->toHaveCount(1);
    expect($phpArticles[0]['slug'])->toBe('post-one');

    // Query notes OR indieweb tagged posts
    $notesOrIndieweb = $this->contentRepo->query(
        (new KindSpecification('note'))->or(new TagSpecification('indieweb'))
    );
    expect($notesOrIndieweb)->toHaveCount(2);

    // Query NOT php
    $notPhp = $this->contentRepo->query(
        (new TagSpecification('php'))->not()
    );
    expect($notPhp)->toHaveCount(1);
    expect($notPhp[0]['slug'])->toBe('post-two');
});
