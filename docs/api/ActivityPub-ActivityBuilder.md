# ActivityBuilder
**Namespace:** `Indieinabox\ActivityPub`

Class ActivityBuilder

Builds ActivityStreams 2.0 objects and activities (Note, Article, Create, Accept, etc.).

## Methods

### buildObjectForPageArray()
`public static function buildObjectForPageArray(string $objectId, string $actorId, string $fqdn, string $content, ?string $name, array $metadata = []): array`

Builds an ActivityStreams object (Note or Article) for a given page/post.

@param string $objectId Unique IRI of the object.
@param string $actorId Actor IRI.
@param string $fqdn Fully qualified domain name of the site.
@param string $content HTML or text content of the post.
@param ?string $name Optional title (promotes Note to Article).
@param array<string, mixed> $metadata Extra metadata (photos, syndication, ratings, read_of, reply, etc.).
@return array<string, mixed>

### buildCreateActivity()
`public static function buildCreateActivity(string $activityId, string $actorId, array $object): array`

Builds a Create activity for an ActivityStreams object.

@param string $activityId Unique IRI of the activity.
@param string $actorId Actor IRI.
@param array<string, mixed> $object The inner object payload.
@return array<string, mixed>

### buildAcceptActivity()
`public static function buildAcceptActivity(string $acceptId, string $actorId, array $followActivity): array`

Builds an Accept activity for a Follow request.

@param string $acceptId Unique IRI of the accept activity.
@param string $actorId Actor IRI.
@param array<string, mixed> $followActivity The received follow activity payload.
@return array<string, mixed>

### buildInteractionActivity()
`public static function buildInteractionActivity(string $activityId, string $type, string $actorId, string $targetUri): array`

Builds a Like or Announce (Repost) activity.

@param string $activityId Unique IRI of the activity.
@param string $type Activity type ('Like' or 'Announce').
@param string $actorId Actor IRI.
@param string $targetUri Remote object URI.
@return array<string, mixed>
