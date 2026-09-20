<?php

declare(strict_types=1);

namespace Indieinabox\Entry;

use DateTimeImmutable;

/**
 * Universal publication and federation entity.
 * Represents articles, notes, replies, pages, and inbound federated entries.
 */
class Entry
{
    // ── 1. Identification & Core Content ──────────────────────────────────────
    private string $id;
    private string $slug;
    private ?string $title;
    private string $content;
    private string $rawContent;
    private ?string $summary;
    private DateTimeImmutable $publishedAt;
    private ?DateTimeImmutable $updatedAt;

    // ── 2. Origin & Federation (Inbound) ──────────────────────────────────────
    private string $sourceNetwork;
    private string $sourceUrl;
    /** @var array{name?: string, url?: string, avatar?: string, handle?: string} */
    private array $author;

    // ── 3. Destination & POSSE (Outbound Syndication) ─────────────────────────
    /** @var string[] */
    private array $syndicationTargets;

    // ── 4. Localization & Multilingual Parity (i18n) ──────────────────────────
    private string $lang;
    private ?string $originalLangUrl;
    /** @var array<string, string> Map of lang code to URL/slug */
    private array $translations;

    // ── 5. Typology & Social Relations (IndieWeb / ActivityStreams) ───────────
    private string $kind;
    /** @var string[] */
    private array $tags;
    private ?string $inReplyTo;
    private ?string $repostOf;
    private ?string $likeOf;
    private ?string $bookmarkOf;

    // ── 6. Media, Visibility, Content Warnings & Polls ────────────────────────
    /** @var array<array{type: string, url: string, alt?: string, mime?: string, size?: int}> */
    private array $attachments;
    private ?string $contentWarning;
    private string $visibility;
    private bool $isDraft;
    /**
     * @var array{
     *     multiple_choice?: bool,
     *     closed?: bool,
     *     expires_at?: string|DateTimeImmutable|null,
     *     total_votes?: int,
     *     options: array<array{title: string, votes?: int}>
     * }|null
     */
    private ?array $poll;
    /** @var array<string, mixed> */
    private array $metadata;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->id = (string) ($data['id'] ?? uniqid('entry_', true));
        $this->slug = (string) ($data['slug'] ?? $this->id);
        $this->title = isset($data['title']) && $data['title'] !== '' ? (string) $data['title'] : null;
        $this->content = (string) ($data['content'] ?? '');
        $this->rawContent = (string) ($data['rawContent'] ?? $data['content'] ?? '');
        $this->summary = isset($data['summary']) && $data['summary'] !== '' ? (string) $data['summary'] : null;

        if (isset($data['publishedAt'])) {
            $this->publishedAt = $data['publishedAt'] instanceof DateTimeImmutable
                ? $data['publishedAt']
                : new DateTimeImmutable(is_numeric($data['publishedAt']) ? '@' . (string) $data['publishedAt'] : (string) $data['publishedAt']);
        } else {
            $this->publishedAt = new DateTimeImmutable();
        }

        if (isset($data['updatedAt']) && $data['updatedAt'] !== null) {
            $this->updatedAt = $data['updatedAt'] instanceof DateTimeImmutable
                ? $data['updatedAt']
                : new DateTimeImmutable(is_numeric($data['updatedAt']) ? '@' . (string) $data['updatedAt'] : (string) $data['updatedAt']);
        } else {
            $this->updatedAt = null;
        }

        $this->sourceNetwork = (string) ($data['sourceNetwork'] ?? 'local');
        $this->sourceUrl = (string) ($data['sourceUrl'] ?? 'local');
        $this->author = (array) ($data['author'] ?? []);

        $this->syndicationTargets = (array) ($data['syndicationTargets'] ?? ['rss', 'atom', 'twtxt']);

        $this->lang = (string) ($data['lang'] ?? 'en');
        $this->originalLangUrl = isset($data['originalLangUrl']) ? (string) $data['originalLangUrl'] : null;
        $this->translations = (array) ($data['translations'] ?? []);

        $this->kind = (string) ($data['kind'] ?? 'note');
        $this->tags = (array) ($data['tags'] ?? []);
        $this->inReplyTo = isset($data['inReplyTo']) ? (string) $data['inReplyTo'] : null;
        $this->repostOf = isset($data['repostOf']) ? (string) $data['repostOf'] : null;
        $this->likeOf = isset($data['likeOf']) ? (string) $data['likeOf'] : null;
        $this->bookmarkOf = isset($data['bookmarkOf']) ? (string) $data['bookmarkOf'] : null;

        $this->attachments = (array) ($data['attachments'] ?? []);
        $this->contentWarning = isset($data['contentWarning']) && $data['contentWarning'] !== '' ? (string) $data['contentWarning'] : null;
        $this->visibility = (string) ($data['visibility'] ?? 'public');
        $this->isDraft = (bool) ($data['isDraft'] ?? false);
        $this->poll = isset($data['poll']) && is_array($data['poll']) ? $data['poll'] : null;
        $this->metadata = (array) ($data['metadata'] ?? []);
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function getId(): string
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getRawContent(): string
    {
        return $this->rawContent;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getPublishedAt(): DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getSourceNetwork(): string
    {
        return $this->sourceNetwork;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    /**
     * @return array{name?: string, url?: string, avatar?: string, handle?: string}
     */
    public function getAuthor(): array
    {
        return $this->author;
    }

    /**
     * @return string[]
     */
    public function getSyndicationTargets(): array
    {
        return $this->syndicationTargets;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function getOriginalLangUrl(): ?string
    {
        return $this->originalLangUrl;
    }

    /**
     * @return array<string, string>
     */
    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getInReplyTo(): ?string
    {
        return $this->inReplyTo;
    }

    public function getRepostOf(): ?string
    {
        return $this->repostOf;
    }

    public function getLikeOf(): ?string
    {
        return $this->likeOf;
    }

    public function getBookmarkOf(): ?string
    {
        return $this->bookmarkOf;
    }

    /**
     * @return array<array{type: string, url: string, alt?: string, mime?: string, size?: int}>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getContentWarning(): ?string
    {
        return $this->contentWarning;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function isDraft(): bool
    {
        return $this->isDraft;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataItem(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    // ── Convenience Helpers ───────────────────────────────────────────────────

    public function isLocal(): bool
    {
        return $this->sourceNetwork === 'local';
    }

    public function isFederated(): bool
    {
        return !$this->isLocal();
    }

    public function isNote(): bool
    {
        return $this->kind === 'note';
    }

    public function isArticle(): bool
    {
        return $this->kind === 'article';
    }

    public function isPage(): bool
    {
        return $this->kind === 'page';
    }

    public function isReply(): bool
    {
        return $this->inReplyTo !== null || $this->kind === 'reply';
    }

    public function isRepost(): bool
    {
        return $this->repostOf !== null || $this->kind === 'repost';
    }

    public function isLike(): bool
    {
        return $this->likeOf !== null || $this->kind === 'like';
    }

    public function hasSensitiveContent(): bool
    {
        return $this->contentWarning !== null;
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function shouldSyndicateTo(string $targetNetwork): bool
    {
        if ($this->isDraft || !$this->isPublic()) {
            return false;
        }

        return in_array($targetNetwork, $this->syndicationTargets, true);
    }

    /**
     * @return array{
     *     multiple_choice?: bool,
     *     closed?: bool,
     *     expires_at?: string|DateTimeImmutable|null,
     *     total_votes?: int,
     *     options: array<array{title: string, votes?: int}>
     * }|null
     */
    public function getPoll(): ?array
    {
        return $this->poll;
    }

    public function hasPoll(): bool
    {
        return $this->poll !== null && !empty($this->poll['options']);
    }

    public function isPollClosed(): bool
    {
        if ($this->poll === null) {
            return false;
        }

        if (!empty($this->poll['closed'])) {
            return true;
        }

        if (!empty($this->poll['expires_at'])) {
            $expiresAt = $this->poll['expires_at'] instanceof DateTimeImmutable
                ? $this->poll['expires_at']
                : new DateTimeImmutable((string) $this->poll['expires_at']);
            return (new DateTimeImmutable()) > $expiresAt;
        }

        return false;
    }

    public function hasTranslations(): bool
    {
        return !empty($this->translations);
    }

    /**
     * Creates a new instance with updated properties (immutability).
     *
     * @param array<string, mixed> $changes
     */
    public function with(array $changes): self
    {
        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
            'rawContent' => $this->rawContent,
            'summary' => $this->summary,
            'publishedAt' => $this->publishedAt,
            'updatedAt' => $this->updatedAt,
            'sourceNetwork' => $this->sourceNetwork,
            'sourceUrl' => $this->sourceUrl,
            'author' => $this->author,
            'syndicationTargets' => $this->syndicationTargets,
            'lang' => $this->lang,
            'originalLangUrl' => $this->originalLangUrl,
            'translations' => $this->translations,
            'kind' => $this->kind,
            'tags' => $this->tags,
            'inReplyTo' => $this->inReplyTo,
            'repostOf' => $this->repostOf,
            'likeOf' => $this->likeOf,
            'bookmarkOf' => $this->bookmarkOf,
            'attachments' => $this->attachments,
            'contentWarning' => $this->contentWarning,
            'visibility' => $this->visibility,
            'isDraft' => $this->isDraft,
            'poll' => $this->poll,
            'metadata' => $this->metadata,
        ];

        return new self(array_merge($data, $changes));
    }

    /**
     * Converts a Page instance to an Entry.
     */
    public static function fromPage(\Indieinabox\Page\Page $page): self
    {
        return $page->toEntry();
    }

    /**
     * Creates an Entry from Twtxt message data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromTwtxt(array $data): self
    {
        $message = (string) ($data['message'] ?? '');
        $publishedAt = isset($data['timestamp'])
            ? ($data['timestamp'] instanceof DateTimeImmutable
                ? $data['timestamp']
                : new DateTimeImmutable(is_numeric($data['timestamp']) ? '@' . (string) $data['timestamp'] : (string) $data['timestamp']))
            : new DateTimeImmutable();

        $nick = (string) ($data['nick'] ?? 'anonymous');
        $url = isset($data['url']) && $data['url'] !== '' ? (string) $data['url'] : null;

        // Extract hashtags: #tag
        preg_match_all('/(?<!\w)#(\w+)/u', $message, $matches);
        $tags = $matches[1] ?? [];

        // Check if message contains a mention: @<nick url> or @&lt;nick url&gt;
        $inReplyTo = null;
        if (preg_match('/@(?:<|&lt;|&amp;lt;)([^\s>&;]+)\s+([^\s>&;]+)(?:>|&gt;|&amp;gt;)/i', $message, $mentionMatches)) {
            $inReplyTo = htmlspecialchars_decode($mentionMatches[2]);
        }

        $html = isset($data['html']) && $data['html'] !== ''
            ? (string) $data['html']
            : \Indieinabox\Twtxt\TwtxtManager::formatMessageToHtml($message);

        return new self([
            'id' => (string) ($data['id'] ?? ('twtxt_' . $publishedAt->getTimestamp() . '_' . substr(md5($message), 0, 8))),
            'title' => null,
            'content' => $html,
            'rawContent' => $message,
            'publishedAt' => $publishedAt,
            'sourceNetwork' => 'twtxt',
            'sourceUrl' => $url ?? 'twtxt',
            'author' => [
                'name' => $nick,
                'handle' => $nick,
                'url' => $url ?? '',
            ],
            'kind' => $inReplyTo !== null ? 'reply' : 'note',
            'inReplyTo' => $inReplyTo,
            'tags' => $tags,
            'syndicationTargets' => [],
            'metadata' => [
                'nick' => $nick,
            ],
        ]);
    }

    /**
     * Dynamic property getter for theme template and retrocompatibility.
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'nick' => $this->author['name'] ?? $this->author['handle'] ?? '',
            'timestamp' => $this->publishedAt,
            'html' => $this->content,
            'message' => $this->rawContent,
            'title' => $this->title,
            'slug' => $this->slug,
            'id' => $this->id,
            'kind' => $this->kind,
            'tags' => $this->tags,
            'content' => $this->content,
            default => $this->metadata[$name] ?? null,
        };
    }

    /**
     * Dynamic property isset check.
     */
    public function __isset(string $name): bool
    {
        return match ($name) {
            'nick' => !empty($this->author['name'] ?? $this->author['handle'] ?? null),
            'timestamp' => true,
            'html', 'content' => $this->content !== '',
            'message' => $this->rawContent !== '',
            'title' => $this->title !== null,
            'slug' => $this->slug !== '',
            'id' => $this->id !== '',
            'kind' => $this->kind !== '',
            'tags' => !empty($this->tags),
            default => isset($this->metadata[$name]),
        };
    }
}

