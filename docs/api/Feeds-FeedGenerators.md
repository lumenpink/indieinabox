# Feed Generators
**Namespace:** `Indieinabox\Feeds`

This module provides pluggable feed generators consuming universal `Entry` entities.

## FeedGeneratorInterface
```php
namespace Indieinabox\Feeds;

use Indieinabox\Site;

interface FeedGeneratorInterface
{
    /**
     * @param \Indieinabox\Entry\Entry[] $entries
     * @param Site $site
     * @param string $lang
     * @return string
     */
    public function generate(array $entries, Site $site, string $lang = 'en'): string;
}
```

## Built-in Implementations

### `Indieinabox\Feeds\Generators\RssFeedGenerator`
Generates fully compliant RSS 2.0 XML with Atom self-links, enclosure elements, and Dublin Core tags.

### `Indieinabox\Feeds\Generators\AtomFeedGenerator`
Generates RFC 4287 compliant Atom 1.0 XML feeds with full HTML content wrappers and author links.

### `Indieinabox\Feeds\Generators\TwtxtFeedGenerator`
Generates plain-text microblogging feeds following the Twtxt format specification (`ISO-8601 \t text`).
