# Architecture & Technical Overview

Indieinabox is a minimalist, self-contained micro-publishing engine engineered for the IndieWeb and the fediverse. It generates static multi-protocol websites (HTML, Gemini, Gopher) while exposing self-hosted, decoupled dynamic services (IndieAuth, Micropub, Microsub, Webmentions, ActivityPub).

## System Architecture

The project follows Domain-Driven Design (DDD) and SOLID principles, structured into modular bounded contexts:

```
                  ┌─────────────────────────────────────────────────┐
                  │                 WebRouter / CLI                 │
                  └───────────────────────┬─────────────────────────┘
                                          │
                  ┌───────────────────────┴─────────────────────────┐
                  │              HTTP Controllers                   │
                  │ (Microsub, Micropub, ActivityPub, Webmention...│
                  └───────────────────────┬─────────────────────────┘
                                          │
       ┌──────────────────────────────────┴─────────────────────────────────┐
       ▼                                  ▼                                 ▼
┌──────────────┐                  ┌──────────────┐                  ┌──────────────┐
│  Federation  │                  │   Services   │                  │ Feed Parsers │
│ (ActivityPub │                  │ (Follow,     │                  │  (Strategy:  │
│   Adapter,   │                  │  Outbox,     │                  │  Twtxt, RSS, │
│  Manager)    │                  │  Microsub,   │                  │  Atom, JSON) │
└──────────────┘                  │  FetchFeeds) │                  └──────────────┘
                                  └──────┬───────┘
                                         ▼
                                  ┌──────────────┐
                                  │ Domain Core  │
                                  │ (Container,  │
                                  │  Models,     │
                                  │  Storage)    │
                                  └──────────────┘
```

## Directory Structure

- **`app/`**: Core application logic and bounded contexts:
  - **`Core/`**: Lightweight PSR-11 Dependency Injection container (`Container.php`) and exception hierarchies.
  - **`Federation/`**: Protocol adapters and federation abstraction layer:
    - `Contracts/FederationAdapter.php`: Unified adapter interface for fediverse protocols.
    - `ActivityPub/ActivityPubAdapter.php`: Native ActivityPub adapter implementation.
    - `FederationManager.php`: Pluggable federation manager orchestrating actors, activities, and protocol delivery.
  - **`Http/`**: HTTP transport layer and static asset delivery:
    - `StaticFileServer.php`: Dedicated HTTP static asset and media file server with ActivityPub content negotiation and MIME resolution.
    - `Controllers/`: Decoupled transport controllers:
      - `AbstractController.php`: Base HTTP controller with JSON, HTML, and redirect responses.
      - `ActivityPubController.php`: ActivityPub actor, inbox, and outbox endpoints (`/actor`, `/inbox`, `/outbox`).
      - `MicropubController.php`: Micropub server and admin client endpoints (`/micropub`, `/micropub/media`, `/micropub/client`).
      - `MicrosubController.php`: Microsub server and web reader endpoints (`/microsub`, `/microsub/reader`).
      - `WebmentionController.php`: Webmention receiver and interactive help form page (`/webmention`).
      - `IndieAuthController.php`: IndieAuth/OAuth server endpoints (`/auth`, `/token`, `/.well-known/oauth-authorization-server`).
      - `AdminController.php`: Dashboard panels (`/admin/config`, `/admin/micropub`, `/admin/microsub`, `/admin/moderation`, `/cron`).
      - `ArchiveController.php`: Web archive explorer and snapshot capture (`/archive`, `/archive/force`).
      - `ConfigController.php`: Administrative site and engine configuration.
  - **`Services/`**: Protocol-agnostic domain business services:
    - `FollowService.php`: Remote follower management, status checks, and distinct fan-out inbox resolution.
    - `OutboxService.php`: Outgoing delivery queueing, follower broadcast fan-out, and adapter-based delivery dispatch.
    - `InboxService.php`: Incoming activity queueing, follow/accept orchestration, and undo-follow processing.
    - `PublishPostService.php`: Note/article creation, markdown persistence, static site compilation, and federation broadcasting.
    - `WebmentionService.php`: Webmention queueing, target validation, verification, and persistence.
    - `ModerationService.php`: Moderation workflows for incoming interactions, comments, and spam handling.
    - `ConfigurationService.php`: Site setup bootstrap, settings persistence, kind taxonomies, translations, and theme installations.
    - `FetchFeedsService.php`: Syndication feed fetching, strategy-based parsing, media caching, and storage.
    - `MicrosubService.php`: Microsub channels, subscriptions, timeline retrieval, read tracking, and social interactions.
  - **`Repositories/`**: Repository Pattern persistence abstractions and contracts:
    - `Contracts/SettingsRepositoryInterface.php`: Storage contract for application settings, kind taxonomies, and translations.
    - `SqliteSettingsRepository.php`: SQLite implementation of settings repository with JSON decoding.
    - `Contracts/InteractionRepositoryInterface.php`: Contract for incoming social interactions querying and moderation.
    - `FileInteractionRepository.php`: Channel file-backed repository for social interactions and moderation.
  - **`Feeds/`**: Feed generation and consumption:
    - `Contracts/FeedParserInterface.php`: Strategy pattern contract for feed parser implementations.
    - `Parsers/TwtxtParser.php`: Strategy parser for Twtxt flat-text feeds.
    - `Parsers/RssParser.php`: Strategy parser for RSS 2.0 feeds.
    - `Parsers/AtomParser.php`: Strategy parser for Atom XML feeds.
    - `Parsers/JsonFeedParser.php`: Strategy parser for JSON Feed 1.1 format.
    - `FeedGeneratorInterface.php`: Generator contract for outgoing syndication feeds.
    - `Generators/`: Feed generation implementations (`RssFeedGenerator`, `AtomFeedGenerator`, `TwtxtFeedGenerator`).
  - **`Microsub/`**: Universal microsub entries and normalization adapters (`ExtendedEntry`, `NormalizationAdapter`).
  - **`Entry/`**: Universal `Entry` domain model for feed items, posts, and federation.
  - **`SiteBuilder/`**: Core site generation services (`ContentScanner`, `TranslationVirtualizer`, `PagePublisher`, `IndexPublisher`, `FeedPublisher`, `AssetPublisher`).
  - **`Markdown/`**: Custom AST parser, processors, validators, and protocol renderers (HTML, Gemtext, Gophermap).
  - **`Theme/`**: Theme metadata, SEO helpers, and microformats components.
  - **`Support/`**: Domain utilities (`TextParser`, `DateFormatter`, `HtmlUtils`, `FileUtils`).
  - **`Taxonomy/`**: Kind helpers and post categorization services (`KindHelper`).
  - **`Localization/`**: Translation services (`Translator`).
  - **`Media/`**: Dithering and image generation services (`ImageProcessor`).
  - **`functions/`**: Procedural helpers and utility functions.
- **`bootstrap/`**: Application bootstrapper.
- **`content/`**: Markdown and plain text source files.
- **`data/`**: SQLite database, cached mentions, and application state.
- **`build.php`**: Entry point orchestrating static site generation.
- **`resources/`**: Theme templates (`views/`) and static assets (`static/`).
- **`public_html/`**: Static HTML output and assets.
- **`public_gopher/`**: Static Gophermap output.
- **`public_gemini/`**: Static Gemini (`.gmi`) output.
- **`docs/`**: Technical documentation and API specifications.
- **`tests/`**: Unit, integration, and functional test suites across 3 tiers using Pest PHP.

## Feature Notes

* **Universal Entry Entity:** All feeds, timelines, and federated items share the same domain entity model (`Indieinabox\Entry\Entry`).
* **Feed Parsers Strategy Pattern:** Swappable, testable strategies (`FeedParserInterface`) handle Twtxt, RSS, Atom, and JSON Feed formats.
* **Offline-first Admin UI:** The admin panel never makes blocking external network requests during page load. All federation, webmentions, and updates run asynchronously.
* **Image Dithering:** Embedded photos are automatically processed and dithered into bandwidth-efficient global palette GIFs and thumbnails.
* **Multi-protocol Publishing:** Every content piece is natively published for the Web (HTML + microformats2 + ActivityPub), Gemini, and Gopher.
