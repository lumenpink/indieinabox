<?php

declare(strict_types=1);

use Indieinabox\Repositories\FileInteractionRepository;

$tempDir = __DIR__ . '/tmp_interactions_repo_unit';

beforeEach(function () use ($tempDir) {
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    $this->repo = new FileInteractionRepository($tempDir);
});

afterEach(function () use ($tempDir) {
    if (is_dir($tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($tempDir);
    }
});

describe('FileInteractionRepository', function () {
    it('saves interactions and queries by slug and status', function () {
        $slug = '/posts/hello-world';
        $hash = md5($slug);
        $id = $hash . '_interaction1';

        $metadata = [
            'author_name' => 'Bob',
            'author_photo' => 'https://example.com/bob.jpg',
            'interaction_type' => 'like',
            'status' => 'approved',
            'target' => 'https://mysite.test/posts/hello-world',
        ];

        $saved = $this->repo->save($id, $metadata, 'Liked this post!');
        expect($saved)->toBeTrue();

        $bySlug = $this->repo->findByPageSlug($slug);
        expect($bySlug)->toHaveCount(1);
        expect($bySlug[0]['author_name'])->toBe('Bob');
        expect($bySlug[0]['interaction_type'])->toBe('like');

        $byStatus = $this->repo->listByStatus('approved');
        expect($byStatus)->toHaveCount(1);
        expect($byStatus[0]['id'])->toBe($id);
    });

    it('updates status and transitions between notifications and spam folders', function () {
        $id = 'interaction_review';
        $metadata = [
            'author_name' => 'Questionable User',
            'status' => 'pending',
            'interaction_type' => 'reply',
        ];

        $this->repo->save($id, $metadata, 'Check out this website');
        $pending = $this->repo->listByStatus('pending');
        expect($pending)->toHaveCount(1);

        // Mark as spam (moves to spam directory)
        $markedSpam = $this->repo->updateStatus($id, 'spam');
        expect($markedSpam)->toBeTrue();
        expect($this->repo->listByStatus('pending'))->toBeEmpty();
        expect($this->repo->listByStatus('spam'))->toHaveCount(1);

        // Revive from spam to approved
        $revived = $this->repo->updateStatus($id, 'approved', 'spam');
        expect($revived)->toBeTrue();
        expect($this->repo->listByStatus('spam'))->toBeEmpty();
        expect($this->repo->listByStatus('approved'))->toHaveCount(1);

        // Delete interaction
        $deleted = $this->repo->delete($id, 'approved');
        expect($deleted)->toBeTrue();
        expect($this->repo->listByStatus('approved'))->toBeEmpty();
    });
});
