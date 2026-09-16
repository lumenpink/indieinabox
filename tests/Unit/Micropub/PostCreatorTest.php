<?php

declare(strict_types=1);

use Indieinabox\Site\Site;
use Indieinabox\Core\Database;
use Indieinabox\Micropub\PostCreator;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_post_creator_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    Database::getDb()->exec($sql);

    $this->site = new Site();
    $this->site->paths->contentDir = $this->tempDir;
    $this->site->fqdn = 'https://example.com';
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('PostCreator discovers post types correctly', function () {
    // Like
    expect(PostCreator::discoverPostType(['like-of' => 'https://other.com/post']))->toBe('like');

    // Reply
    expect(PostCreator::discoverPostType(['in-reply-to' => 'https://other.com/post']))->toBe('reply');

    // Repost
    expect(PostCreator::discoverPostType(['repost-of' => 'https://other.com/post']))->toBe('repost');

    // Photo
    expect(PostCreator::discoverPostType([], ['https://example.com/media/test.jpg']))->toBe('photo');

    // Article (with name)
    expect(PostCreator::discoverPostType(['name' => 'My Article Title']))->toBe('article');

    // Note (default)
    expect(PostCreator::discoverPostType(['content' => 'Just a quick note']))->toBe('note');
});

test('PostCreator slugify generates clean URL slugs', function () {
    expect(PostCreator::slugify('Hello World! 2026'))->toBe('hello-world-2026');
    expect(PostCreator::slugify('ação e coração'))->toBe('acao-e-coracao');
    expect(PostCreator::slugify('   '))->toBe('n-a');
});

test('PostCreator creates markdown file with frontmatter and extracts hashtags', function () {
    /** @var \Tests\TestCase $this */
    $input = [
        'name' => 'IndieWeb Architecture',
        'content' => 'Building decentralized systems with #indieweb and #php.',
        'category' => ['architecture'],
        'mp-slug' => 'indieweb-architecture',
    ];

    $res = PostCreator::create($this->site, $input);
    expect($res['status'])->toBe(202);
    expect($res['kind'])->toBe('article');
    expect($res['slug'])->toBe('indieweb-architecture');
    expect($res['post_url'])->toContain('/article/');
    expect(file_exists($res['file_path']))->toBeTrue();

    $content = file_get_contents($res['file_path']);
    expect($content)->toContain('title: "IndieWeb Architecture"')
        ->toContain('indieweb')
        ->toContain('php')
        ->toContain('architecture');
});
