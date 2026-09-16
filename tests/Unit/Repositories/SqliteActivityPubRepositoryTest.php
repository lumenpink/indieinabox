<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Repositories\SqliteActivityPubRepository;

$tempDir = __DIR__ . '/tmp_ap_repo_unit';

beforeEach(function () use ($tempDir) {
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    Database::disconnect();
    $dbPath = $tempDir . '/test.sqlite';
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
    Database::$dataDir = $tempDir . '/data';
    Database::connect($dbPath);
    $db = Database::getDb();
    $schema = file_get_contents(__DIR__ . '/../../../database.sql');
    $db->exec($schema);
});

afterEach(function () use ($tempDir) {
    Database::disconnect();
    Database::$dataDir = '';

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

describe('SqliteActivityPubRepository', function () {
    it('manages followers lifecycle', function () {
        $repo = new SqliteActivityPubRepository();

        expect($repo->getFollowers())->toBeEmpty();
        expect($repo->isFollower('https://remote.social/users/alice'))->toBeFalse();

        expect($repo->addFollower('https://remote.social/users/alice', 'https://remote.social/users/alice/inbox', 'https://remote.social/inbox'))->toBeTrue();
        expect($repo->isFollower('https://remote.social/users/alice'))->toBeTrue();

        $followers = $repo->getFollowers();
        expect($followers)->toHaveCount(1);
        expect($followers[0]['actor_url'])->toBe('https://remote.social/users/alice');
        expect($followers[0]['inbox_url'])->toBe('https://remote.social/users/alice/inbox');
        expect($followers[0]['shared_inbox_url'])->toBe('https://remote.social/inbox');

        $inboxes = $repo->getDistinctInboxes();
        expect($inboxes)->toContain('https://remote.social/inbox');

        // Removing follower
        expect($repo->removeFollower('https://remote.social/users/alice'))->toBeTrue();
        expect($repo->getFollowers())->toBeEmpty();
        expect($repo->isFollower('https://remote.social/users/alice'))->toBeFalse();
    });

    it('manages outbox queue lifecycle and status transitions', function () {
        $repo = new SqliteActivityPubRepository();

        expect($repo->getPendingOutbox())->toBeEmpty();

        $id = $repo->enqueueOutbox('{"type":"Create"}', 'https://remote.social/inbox', time());
        expect($id)->toBeGreaterThan(0);

        $pending = $repo->getPendingOutbox();
        expect($pending)->toHaveCount(1);
        expect($pending[0]['id'])->toBe($id);
        expect($pending[0]['target_inbox'])->toBe('https://remote.social/inbox');

        // Update status to failed
        expect($repo->updateOutboxStatus($id, 'failed'))->toBeTrue();
        expect($repo->getPendingOutbox())->toBeEmpty();

        // Update status to sent
        expect($repo->updateOutboxStatus($id, 'sent'))->toBeTrue();

        // Pruning older records
        $future = time() + 3600;
        $pruned = $repo->pruneOutbox($future);
        expect($pruned)->toBe(1);
    });
});
