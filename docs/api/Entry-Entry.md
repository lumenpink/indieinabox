# Entry
**Namespace:** `Indieinabox\Entry`

Class Entry

The universal domain model representing a content item across all protocols:
local static site pages, incoming and outgoing syndicated feeds (RSS, Atom, Twtxt),
and federated social posts (ActivityPub, Microsub).

## Properties

- `$id` (string): Unique identifier or URI.
- `$url` (string): Canonical public web URL.
- `$title` (?string): Item title, or null if titleless (e.g. note or status update).
- `$content` (string): Formatted HTML content body.
- `$published` (DateTimeImmutable): Publication timestamp.
- `$updated` (?DateTimeImmutable): Last modification timestamp.
- `$authorName` (?string): Author's display name.
- `$authorUrl` (?string): Author's homepage or profile URI.
- `$authorAvatar` (?string): Author's profile photo or avatar URL.
- `$summary` (?string): Plain text or brief summary.
- `$tags` (array): Array of string category tags.
- `$media` (array): Attached media files and attachments.
- `$extra` (array): Protocol-specific extra metadata (e.g. `in_reply_to`, `twtxt_nick`, `visibility`).
- `$contentWarning` (?string): Content advisory or spoiler warning text.
- `$poll` (?array): Structured poll data (options, voter counts, expiration).

## Methods

### fromPage()
```php
public static function fromPage(Page $page, Site $site): self
```
Factory creating an `Entry` from an internal `Page` object.

### fromTwtxt()
```php
public static function fromTwtxt(string $date, string $text, ?string $nick = null, ?string $url = null): self
```
Factory creating an `Entry` from a raw Twtxt feed line.

### toArray()
```php
public function toArray(): array
```
Exports properties into a standardized associative array.

### Magic Accessors
Implements `__get` and `__isset` for seamless property access and backwards compatibility with feed templates.
