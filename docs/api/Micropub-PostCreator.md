# PostCreator
**Namespace:** `Indieinabox\Micropub`

Class PostCreator

Handles creation of new IndieWeb posts from Micropub input data,
post type discovery, frontmatter generation, and publication queuing.

## Methods

### create()
`public static function create(Indieinabox\Site\Site $site, array $input, ?Indieinabox\Repositories\Contracts\ContentRepositoryInterface $contentRepo = null): array`

Creates a new post on disk from Micropub input and enqueues federation events.

@param Site $site Global site configuration.
@param array<string, mixed> $input Form or JSON input payload.
@return array{status: int, headers: array<string, string>, post_url: string, file_path: string, kind: string, slug: string}

### discoverPostType()
`public static function discoverPostType(array $input, array $photos = []): string`

Discovers the post kind based on IndieWeb properties, photos, and title.

@param array<string, mixed> $input
@param array<int, mixed> $photos
@return string

### slugify()
`public static function slugify(string $text): string`

Converts a string into a URL-friendly slug.

@param string $text
@return string
