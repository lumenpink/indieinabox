<?php

declare(strict_types=1);

use Indieinabox\Repositories\Contracts\ContentRepositoryInterface;
use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/content_repo_unit_' . uniqid('', true);
    mkdir($this->tempDir . '/content', 0755, true);

    $paths = new Paths(
        $this->tempDir,
        $this->tempDir . '/public_html',
        $this->tempDir . '/public_gemini',
        $this->tempDir . '/public_gopher',
        $this->tempDir . '/public_media',
        'content',
        'resources'
    );
    $this->site = new Site(null, $paths);
    $this->repo = new FileSystemContentRepository($this->tempDir . '/content', $this->site);
});

afterEach(function () {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

describe('FileSystemContentRepository', function () {
    it('implements ContentRepositoryInterface', function () {
        expect($this->repo)->toBeInstanceOf(ContentRepositoryInterface::class);
    });

    it('builds and parses frontmatter markdown format', function () {
        $frontmatter = [
            'title' => 'Sample Article',
            'date' => '2026-09-16 12:00:00',
            'tags' => ['indieweb', 'php'],
            'draft' => false,
        ];
        $body = 'This is the article body content.';

        $markdown = $this->repo->buildFrontmatterMarkdown($frontmatter, $body);

        expect($markdown)->toContain('title: "Sample Article"');
        expect($markdown)->toContain('draft: false');
        expect($markdown)->toContain('tags:');
        expect($markdown)->toContain('  - indieweb');
        expect($markdown)->toContain('This is the article body content.');

        $parsed = $this->repo->parseFrontmatterMarkdown($markdown);
        expect($parsed['frontmatter']['title'])->toBe('Sample Article');
        expect($parsed['frontmatter']['draft'])->toBeFalse();
        expect($parsed['frontmatter']['tags'])->toBe(['indieweb', 'php']);
        expect(trim($parsed['body']))->toBe($body);
    });

    it('saves a post file, checks existence, reads content, and deletes it', function () {
        $filepath = $this->repo->save(
            'articles',
            'my-first-post',
            'Hello world content',
            ['title' => 'My First Post'],
            null,
            '2026',
            '09'
        );

        expect(file_exists($filepath))->toBeTrue();
        expect($this->repo->exists($filepath))->toBeTrue();

        $content = $this->repo->findByPath($filepath);
        expect($content)->not->toBeNull();
        expect($content)->toContain('title: "My First Post"');
        expect($content)->toContain('Hello world content');

        // Non-existent path
        expect($this->repo->findByPath('/non/existent/file.md'))->toBeNull();

        // Delete
        expect($this->repo->delete($filepath))->toBeTrue();
        expect($this->repo->exists($filepath))->toBeFalse();
        expect($this->repo->delete($filepath))->toBeFalse();
    });

    it('resolves unique slugs avoiding file collisions', function () {
        // Save initial file
        $this->repo->save(
            'notes',
            'quick-note',
            'First note',
            [],
            null,
            '2026',
            '09'
        );

        // Next slug should have suffix -1
        $unique1 = $this->repo->generateUniqueSlug('notes', 'quick-note', null, '2026', '09');
        expect($unique1)->toBe('quick-note-1');

        $this->repo->save('notes', $unique1, 'Second note', [], null, '2026', '09');

        // Next slug should have suffix -2
        $unique2 = $this->repo->generateUniqueSlug('notes', 'quick-note', null, '2026', '09');
        expect($unique2)->toBe('quick-note-2');

        // Numeric slug collision
        $this->repo->save('notes', '100', 'Numeric note', [], null, '2026', '09');
        $numUnique = $this->repo->generateUniqueSlug('notes', '100', null, '2026', '09');
        expect($numUnique)->toBe('101');
    });

    it('scans content directory recursively finding all markdown files', function () {
        $this->repo->save('articles', 'post-a', 'Post A', [], null, '2026', '01');
        $this->repo->save('articles', 'post-b', 'Post B', [], null, '2026', '02');
        $this->repo->save('notes', 'note-c', 'Note C', [], null, '2026', '03');

        // Create a non-markdown file that should be ignored
        file_put_contents($this->tempDir . '/content/articles/ignore.txt', 'Ignored');

        $scanned = $this->repo->scan($this->tempDir . '/content');
        expect($scanned)->toHaveCount(3);
        foreach ($scanned as $file) {
            expect($file)->toEndWith('.md');
        }
    });
});
