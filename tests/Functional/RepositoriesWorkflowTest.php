<?php

declare(strict_types=1);

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Page\Page;
use Indieinabox\Repositories\Contracts\InteractionRepositoryInterface;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Services\ConfigurationService;
use Indieinabox\Services\ModerationService;
use Indieinabox\Taxonomy\KindHelper;

$funcTempDir = __DIR__ . '/tmp_functional_repos_workflow';

beforeEach(function () use ($funcTempDir) {
    if (!is_dir($funcTempDir)) {
        mkdir($funcTempDir, 0777, true);
    }

    Database::disconnect();
    $testDbPath = $funcTempDir . '/test.sqlite';
    if (file_exists($testDbPath)) {
        unlink($testDbPath);
    }
    Database::$dataDir = $funcTempDir . '/data';
    Database::connect($testDbPath);
    $db = Database::getDb();

    $schema = file_get_contents(__DIR__ . '/../../database.sql');
    $db->exec($schema);

    Container::getInstance()->flush();
});

afterEach(function () use ($funcTempDir) {
    Database::disconnect();
    Database::$dataDir = '';
    Container::getInstance()->flush();

    if (is_dir($funcTempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($funcTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($funcTempDir);
    }
});

describe('Repositories and Services Workflow', function () {
    it('coordinates site configuration, interaction persistence, and moderation approval', function () {
        $container = Container::getInstance();

        // 1. Resolve and bootstrap site config via ConfigurationService
        $configService = $container->make(ConfigurationService::class);
        $configService->bootstrap('supersecret', 'Indie Site', 'https://indie.example.com');

        expect($configService->getSettingsRepository()->get('sitename'))->toBe('Indie Site');
        expect(Database::getSetting('sitename'))->toBe('Indie Site');

        // 2. Save kinds and translations
        $configService->saveKinds([
            'article' => ['title' => 'Longform Articles', 'layout' => 'standard'],
            'note' => ['title' => 'Quick Notes', 'layout' => 'micro'],
        ]);
        expect(Database::getKinds())->toHaveKey('article');

        // 3. Persist incoming interactions for a page
        $interactionRepo = $container->make(InteractionRepositoryInterface::class);
        $pageSlug = 'articles/first-post';
        $pageHash = md5($pageSlug);

        // Interaction 1: Pending like
        $likeId = $pageHash . '_like1';
        $interactionRepo->save($likeId, [
            'author_name' => 'Reader One',
            'interaction_type' => 'like',
            'status' => 'pending',
            'target' => 'https://indie.example.com/articles/first-post',
        ], '');

        // Interaction 2: Pending reply
        $replyId = $pageHash . '_reply1';
        $interactionRepo->save($replyId, [
            'author_name' => 'Commenter Two',
            'interaction_type' => 'reply',
            'status' => 'pending',
            'target' => 'https://indie.example.com/articles/first-post',
        ], 'Great article!');

        // 4. Moderate: Approve the like, leave reply pending
        $modService = $container->make(ModerationService::class);
        $pendingList = $modService->listInteractions('pending');
        expect($pendingList)->toHaveCount(2);

        $approved = $modService->approveInteraction($likeId, 'pending');
        expect($approved)->toBeTrue();

        // 5. Query interactions through KindHelper on a Page object
        $page = new Page();
        $page->slug = $pageSlug;

        // Only approved interactions should appear on the page
        $pageInteractions = KindHelper::getInteractions($page);
        expect($pageInteractions)->toHaveCount(1);
        expect($pageInteractions[0]['author_name'])->toBe('Reader One');
        expect($pageInteractions[0]['interaction_type'])->toBe('like');

        // Filtering by type
        $likesOnly = KindHelper::getInteractions($page, 'like');
        expect($likesOnly)->toHaveCount(1);
        $repliesOnly = KindHelper::getInteractions($page, 'reply');
        expect($repliesOnly)->toBeEmpty();
    });
});
