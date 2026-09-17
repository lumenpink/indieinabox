# InteractionDto
**Namespace:** `Indieinabox\DTO`

Data Transfer Object representing a normalized social interaction (Webmention or ActivityPub).

## Properties

### `private string $id`

### `private string $target`

### `private string $source`

### `private string $type`

### `private string $protocol`

### `private string $authorName`

### `private ?string $authorUrl`

### `private ?string $authorPhoto`

### `private string $content`

### `private DateTimeImmutable $publishedAt`

### `private string $status`

### `private array $metadata`

/** @var array<string, mixed> */

## Methods

### __construct()
`public function __construct(string $id, string $target, string $source, string $type, string $protocol, string $authorName, ?string $authorUrl, ?string $authorPhoto, string $content, DateTimeImmutable $publishedAt, string $status = 'pending', array $metadata = [])`

@param array<string, mixed> $metadata

### getId()
`public function getId(): string`

### getTarget()
`public function getTarget(): string`

### getSource()
`public function getSource(): string`

### getType()
`public function getType(): string`

### getProtocol()
`public function getProtocol(): string`

### getAuthorName()
`public function getAuthorName(): string`

### getAuthorUrl()
`public function getAuthorUrl(): ?string`

### getAuthorPhoto()
`public function getAuthorPhoto(): ?string`

### getContent()
`public function getContent(): string`

### getPublishedAt()
`public function getPublishedAt(): DateTimeImmutable`

### getStatus()
`public function getStatus(): string`

### getMetadata()
`public function getMetadata(): array`

@return array<string, mixed>

### fromWebmention()
`public static function fromWebmention(string $source, string $target, array $verifiedContent, string $status = 'pending'): Indieinabox\DTO\InteractionDto`

Creates an InteractionDto from verified Webmention content.

@param string $source
@param string $target
@param array<string, mixed> $verifiedContent
@param string $status
@return self

### fromActivityPub()
`public static function fromActivityPub(array $activity, ?array $actorData = null, string $status = 'pending'): ?Indieinabox\DTO\InteractionDto`

Creates an InteractionDto from an ActivityPub activity.

@param array<string, mixed> $activity
@param array<string, mixed>|null $actorData
@param string $status
@return self|null

### fromArray()
`public static function fromArray(array $data): Indieinabox\DTO\InteractionDto`

Constructs an InteractionDto from an associative array.

@param array<string, mixed> $data
@return self

### toArray()
`public function toArray(): array`

Converts the DTO to an associative array representation compatible with storage and frontmatter.

@return array<string, mixed>
