# Entry
**Namespace:** `Indieinabox\Entry`

Universal publication and federation entity.
Represents articles, notes, replies, pages, and inbound federated entries.

## Properties

### `private string $id`

### `private string $slug`

### `private ?string $title`

### `private string $content`

### `private string $rawContent`

### `private ?string $summary`

### `private DateTimeImmutable $publishedAt`

### `private ?DateTimeImmutable $updatedAt`

### `private string $sourceNetwork`

### `private string $sourceUrl`

### `private array $author`

/** @var array{name?: string, url?: string, avatar?: string, handle?: string} */

### `private array $syndicationTargets`

/** @var string[] */

### `private string $lang`

### `private ?string $originalLangUrl`

### `private array $translations`

/** @var array<string, string> Map of lang code to URL/slug */

### `private string $kind`

### `private array $tags`

/** @var string[] */

### `private ?string $inReplyTo`

### `private ?string $repostOf`

### `private ?string $likeOf`

### `private ?string $bookmarkOf`

### `private array $attachments`

/** @var array<array{type: string, url: string, alt?: string, mime?: string, size?: int}> */

### `private ?string $contentWarning`

### `private string $visibility`

### `private bool $isDraft`

### `private ?array $poll`

@var array{
    multiple_choice?: bool,
    closed?: bool,
    expires_at?: string|DateTimeImmutable|null,
    total_votes?: int,
    options: array<array{title: string, votes?: int}>
}|null

### `private array $metadata`

/** @var array<string, mixed> */

## Methods

### __construct()
`public function __construct(array $data = [])`

@param array<string, mixed> $data

### getId()
`public function getId(): string`

### getSlug()
`public function getSlug(): string`

### getTitle()
`public function getTitle(): ?string`

### getContent()
`public function getContent(): string`

### getRawContent()
`public function getRawContent(): string`

### getSummary()
`public function getSummary(): ?string`

### getPublishedAt()
`public function getPublishedAt(): DateTimeImmutable`

### getUpdatedAt()
`public function getUpdatedAt(): ?DateTimeImmutable`

### getSourceNetwork()
`public function getSourceNetwork(): string`

### getSourceUrl()
`public function getSourceUrl(): string`

### getAuthor()
`public function getAuthor(): array`

@return array{name?: string, url?: string, avatar?: string, handle?: string}

### getSyndicationTargets()
`public function getSyndicationTargets(): array`

@return string[]

### getLang()
`public function getLang(): string`

### getOriginalLangUrl()
`public function getOriginalLangUrl(): ?string`

### getTranslations()
`public function getTranslations(): array`

@return array<string, string>

### getKind()
`public function getKind(): string`

### getTags()
`public function getTags(): array`

@return string[]

### getInReplyTo()
`public function getInReplyTo(): ?string`

### getRepostOf()
`public function getRepostOf(): ?string`

### getLikeOf()
`public function getLikeOf(): ?string`

### getBookmarkOf()
`public function getBookmarkOf(): ?string`

### getAttachments()
`public function getAttachments(): array`

@return array<array{type: string, url: string, alt?: string, mime?: string, size?: int}>

### getContentWarning()
`public function getContentWarning(): ?string`

### getVisibility()
`public function getVisibility(): string`

### isDraft()
`public function isDraft(): bool`

### getMetadata()
`public function getMetadata(): array`

@return array<string, mixed>

### getMetadataItem()
`public function getMetadataItem(string $key, ?mixed $default = null): ?mixed`

### isLocal()
`public function isLocal(): bool`

### isFederated()
`public function isFederated(): bool`

### isNote()
`public function isNote(): bool`

### isArticle()
`public function isArticle(): bool`

### isPage()
`public function isPage(): bool`

### isReply()
`public function isReply(): bool`

### isRepost()
`public function isRepost(): bool`

### isLike()
`public function isLike(): bool`

### hasSensitiveContent()
`public function hasSensitiveContent(): bool`

### isPublic()
`public function isPublic(): bool`

### shouldSyndicateTo()
`public function shouldSyndicateTo(string $targetNetwork): bool`

### getPoll()
`public function getPoll(): ?array`

@return array{
    multiple_choice?: bool,
    closed?: bool,
    expires_at?: string|DateTimeImmutable|null,
    total_votes?: int,
    options: array<array{title: string, votes?: int}>
}|null

### hasPoll()
`public function hasPoll(): bool`

### isPollClosed()
`public function isPollClosed(): bool`

### hasTranslations()
`public function hasTranslations(): bool`

### with()
`public function with(array $changes): Indieinabox\Entry\Entry`

Creates a new instance with updated properties (immutability).

@param array<string, mixed> $changes

### fromPage()
`public static function fromPage(Indieinabox\Page $page): Indieinabox\Entry\Entry`

Converts a Page instance to an Entry.

### fromTwtxt()
`public static function fromTwtxt(array $data): Indieinabox\Entry\Entry`

Creates an Entry from Twtxt message data.

@param array<string, mixed> $data

### __get()
`public function __get(string $name): ?mixed`

Dynamic property getter for theme template and retrocompatibility.

### __isset()
`public function __isset(string $name): bool`

Dynamic property isset check.
