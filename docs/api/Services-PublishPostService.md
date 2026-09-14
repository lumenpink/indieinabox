# PublishPostService
**Namespace:** `Indieinabox\Services`

Protocol-agnostic service to compose, persist, build, and syndicate new posts and notes.

## Properties

### `private Indieinabox\Site $site`

### `private ?Indieinabox\Services\OutboxService $outboxService`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\Services\OutboxService $outboxService = null)`

### publish()
`public function publish(string $text, array $mediaPaths = [], string $kind = 'note', ?string $title = null): array`

Publishes a note or article and triggers static build and federation delivery.

@param string $text Content body of the post
@param array<int, string> $mediaPaths List of local media paths
@param string $kind 'note' or 'article'
@param string|null $title Optional title (for articles)
@return array{slug: string, filepath: string}
