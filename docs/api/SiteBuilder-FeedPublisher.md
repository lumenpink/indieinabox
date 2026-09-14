# FeedPublisher
**Namespace:** `Indieinabox\SiteBuilder`

Class FeedPublisher

Generates syndicated feeds (RSS 2.0, Atom 1.0, Twtxt) across active languages.
Consumes universal `Entry` entities and delegates XML/text generation to pluggable `FeedGeneratorInterface` implementations.

## Methods

### __construct()
```php
public function __construct(Site $site, array $generators = [])
```
Initializes the feed publisher with site settings and registered feed generators.
By default, registers `RssFeedGenerator`, `AtomFeedGenerator`, and `TwtxtFeedGenerator`.

### publishFeeds()
```php
public function publishFeeds(Pages $pages): void
```
Compiles and writes feed files for all configured languages (root for default language,
subfolders for secondary languages). Registers generated paths into `SiteBuilder::$manifest`.

### registerGenerator()
```php
public function registerGenerator(string $format, FeedGeneratorInterface $generator): void
```
Registers a custom feed generator implementation.
