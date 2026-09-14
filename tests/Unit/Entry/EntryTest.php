<?php

declare(strict_types=1);

use Indieinabox\Entry\Entry;

it('creates Entry with default values', function () {
    $entry = new Entry();

    expect($entry->getId())->toBeString();
    expect($entry->getSlug())->toBe($entry->getId());
    expect($entry->getTitle())->toBeNull();
    expect($entry->getContent())->toBe('');
    expect($entry->getRawContent())->toBe('');
    expect($entry->getSummary())->toBeNull();
    expect($entry->getPublishedAt())->toBeInstanceOf(\DateTimeImmutable::class);
    expect($entry->getUpdatedAt())->toBeNull();

    expect($entry->getSourceNetwork())->toBe('local');
    expect($entry->getSourceUrl())->toBe('local');
    expect($entry->getAuthor())->toBeEmpty();
    expect($entry->getSyndicationTargets())->toBe(['rss', 'atom', 'twtxt']);

    expect($entry->getLang())->toBe('en');
    expect($entry->getOriginalLangUrl())->toBeNull();
    expect($entry->getTranslations())->toBeEmpty();

    expect($entry->getKind())->toBe('note');
    expect($entry->getTags())->toBeEmpty();
    expect($entry->getInReplyTo())->toBeNull();
    expect($entry->getRepostOf())->toBeNull();
    expect($entry->getLikeOf())->toBeNull();
    expect($entry->getBookmarkOf())->toBeNull();

    expect($entry->getAttachments())->toBeEmpty();
    expect($entry->getContentWarning())->toBeNull();
    expect($entry->getVisibility())->toBe('public');
    expect($entry->isDraft())->toBeFalse();
    expect($entry->getMetadata())->toBeEmpty();

    expect($entry->isLocal())->toBeTrue();
    expect($entry->isFederated())->toBeFalse();
    expect($entry->isNote())->toBeTrue();
    expect($entry->isArticle())->toBeFalse();
    expect($entry->isPublic())->toBeTrue();
    expect($entry->hasSensitiveContent())->toBeFalse();
    expect($entry->hasTranslations())->toBeFalse();
});

it('creates Entry with full custom values', function () {
    $published = new \DateTimeImmutable('2026-09-13 12:00:00');
    $updated = new \DateTimeImmutable('2026-09-13 13:00:00');

    $entry = new Entry([
        'id' => 'post-123',
        'slug' => 'artigo-solid',
        'title' => 'Refatoração SOLID no Indieinabox',
        'content' => '<p>Conteúdo HTML</p>',
        'rawContent' => '# Conteúdo Markdown',
        'summary' => 'Resumo do artigo',
        'publishedAt' => $published,
        'updatedAt' => $updated,
        'sourceNetwork' => 'activitypub',
        'sourceUrl' => 'https://mastodon.social/@user/123',
        'author' => [
            'name' => 'Lumen',
            'url' => 'https://lumen.pink',
            'avatar' => 'https://lumen.pink/avatar.png',
            'handle' => '@lumen@mastodon.social',
        ],
        'syndicationTargets' => ['rss', 'activitypub'],
        'lang' => 'pt',
        'originalLangUrl' => '/posts/artigo-solid/',
        'translations' => [
            'en' => '/en/posts/solid-refactoring/',
        ],
        'kind' => 'article',
        'tags' => ['solid', 'php', 'indieweb'],
        'inReplyTo' => 'https://example.com/other-post',
        'repostOf' => 'https://example.com/boosted-post',
        'likeOf' => 'https://example.com/liked-post',
        'bookmarkOf' => 'https://example.com/bookmarked-url',
        'attachments' => [
            ['type' => 'image', 'url' => '/media/image.png', 'alt' => 'Diagrama SOLID'],
        ],
        'contentWarning' => 'Contém spoilers',
        'visibility' => 'unlisted',
        'isDraft' => false,
        'metadata' => [
            'custom_key' => 'custom_val',
        ],
    ]);

    expect($entry->getId())->toBe('post-123');
    expect($entry->getSlug())->toBe('artigo-solid');
    expect($entry->getTitle())->toBe('Refatoração SOLID no Indieinabox');
    expect($entry->getContent())->toBe('<p>Conteúdo HTML</p>');
    expect($entry->getRawContent())->toBe('# Conteúdo Markdown');
    expect($entry->getSummary())->toBe('Resumo do artigo');
    expect($entry->getPublishedAt())->toBe($published);
    expect($entry->getUpdatedAt())->toBe($updated);

    expect($entry->getSourceNetwork())->toBe('activitypub');
    expect($entry->getSourceUrl())->toBe('https://mastodon.social/@user/123');
    expect($entry->getAuthor()['name'])->toBe('Lumen');
    expect($entry->getAuthor()['handle'])->toBe('@lumen@mastodon.social');

    expect($entry->getSyndicationTargets())->toBe(['rss', 'activitypub']);
    expect($entry->getLang())->toBe('pt');
    expect($entry->getOriginalLangUrl())->toBe('/posts/artigo-solid/');
    expect($entry->getTranslations())->toBe(['en' => '/en/posts/solid-refactoring/']);
    expect($entry->hasTranslations())->toBeTrue();

    expect($entry->getKind())->toBe('article');
    expect($entry->isArticle())->toBeTrue();
    expect($entry->isNote())->toBeFalse();
    expect($entry->isReply())->toBeTrue();
    expect($entry->isRepost())->toBeTrue();
    expect($entry->isLike())->toBeTrue();
    expect($entry->isLocal())->toBeFalse();
    expect($entry->isFederated())->toBeTrue();
    expect($entry->hasSensitiveContent())->toBeTrue();
    expect($entry->getContentWarning())->toBe('Contém spoilers');
    expect($entry->getVisibility())->toBe('unlisted');
    expect($entry->isPublic())->toBeFalse();

    expect($entry->getAttachments())->toHaveCount(1);
    expect($entry->getAttachments()[0]['alt'])->toBe('Diagrama SOLID');
    expect($entry->getMetadataItem('custom_key'))->toBe('custom_val');
    expect($entry->getMetadataItem('non_existent', 'default'))->toBe('default');
});

it('correctly determines syndication suitability', function () {
    $publicEntry = new Entry([
        'syndicationTargets' => ['rss', 'twtxt'],
        'visibility' => 'public',
        'isDraft' => false,
    ]);

    expect($publicEntry->shouldSyndicateTo('rss'))->toBeTrue();
    expect($publicEntry->shouldSyndicateTo('twtxt'))->toBeTrue();
    expect($publicEntry->shouldSyndicateTo('activitypub'))->toBeFalse();

    $draftEntry = $publicEntry->with(['isDraft' => true]);
    expect($draftEntry->shouldSyndicateTo('rss'))->toBeFalse();

    $privateEntry = $publicEntry->with(['visibility' => 'private']);
    expect($privateEntry->shouldSyndicateTo('rss'))->toBeFalse();
});

it('creates updated immutable copy using with()', function () {
    $entry = new Entry([
        'title' => 'Original Title',
        'lang' => 'pt',
    ]);

    $modified = $entry->with([
        'title' => 'New Title',
        'lang' => 'en',
    ]);

    expect($entry->getTitle())->toBe('Original Title');
    expect($entry->getLang())->toBe('pt');

    expect($modified->getTitle())->toBe('New Title');
    expect($modified->getLang())->toBe('en');
    expect($modified->getId())->toBe($entry->getId());
});

it('supports polls and poll status checks', function () {
    $entryWithoutPoll = new Entry();
    expect($entryWithoutPoll->hasPoll())->toBeFalse();
    expect($entryWithoutPoll->getPoll())->toBeNull();
    expect($entryWithoutPoll->isPollClosed())->toBeFalse();

    $openPollEntry = new Entry([
        'poll' => [
            'multiple_choice' => false,
            'closed' => false,
            'expires_at' => (new \DateTimeImmutable('+1 day'))->format('c'),
            'total_votes' => 15,
            'options' => [
                ['title' => 'Opção A', 'votes' => 5],
                ['title' => 'Opção B', 'votes' => 10],
            ],
        ],
    ]);

    expect($openPollEntry->hasPoll())->toBeTrue();
    expect($openPollEntry->getPoll()['options'])->toHaveCount(2);
    expect($openPollEntry->isPollClosed())->toBeFalse();

    $closedPollEntry = $openPollEntry->with([
        'poll' => [
            'multiple_choice' => false,
            'closed' => true,
            'options' => [
                ['title' => 'Opção A', 'votes' => 5],
            ],
        ],
    ]);
    expect($closedPollEntry->isPollClosed())->toBeTrue();

    $expiredPollEntry = $openPollEntry->with([
        'poll' => [
            'multiple_choice' => false,
            'closed' => false,
            'expires_at' => (new \DateTimeImmutable('-1 day'))->format('c'),
            'options' => [
                ['title' => 'Opção A', 'votes' => 5],
            ],
        ],
    ]);
    expect($expiredPollEntry->isPollClosed())->toBeTrue();
});

it('creates Entry from twtxt data with hashtags, mentions, and dynamic properties', function () {
    $now = new \DateTimeImmutable('2026-09-13 15:30:00');
    $entry = Entry::fromTwtxt([
        'timestamp' => $now,
        'nick' => 'alice',
        'url' => 'https://alice.com/twtxt.txt',
        'message' => 'Hello @<bob https://bob.com/twtxt.txt> check #php and #indieweb',
    ]);

    expect($entry->isFederated())->toBeTrue();
    expect($entry->getSourceNetwork())->toBe('twtxt');
    expect($entry->getAuthor()['name'])->toBe('alice');
    expect($entry->getAuthor()['url'])->toBe('https://alice.com/twtxt.txt');
    expect($entry->getPublishedAt())->toBe($now);
    expect($entry->getTags())->toBe(['php', 'indieweb']);
    expect($entry->isReply())->toBeTrue();

    // Test dynamic property access (__get and __isset)
    expect($entry->nick)->toBe('alice');
    expect($entry->timestamp)->toBe($now);
    expect($entry->message)->toBe('Hello @<bob https://bob.com/twtxt.txt> check #php and #indieweb');
    expect($entry->html)->toContain('<a href="https://bob.com/twtxt.txt" class="mention">@bob</a>');
    expect($entry->kind)->toBe('reply');
    expect(isset($entry->nick))->toBeTrue();
    expect(isset($entry->timestamp))->toBeTrue();
    expect(isset($entry->non_existent))->toBeFalse();
});

