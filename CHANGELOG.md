# Changelog

## [Unreleased]

### Features
  - check for database configuration before running cron worker
  - (docker) configure non-root user 1000:1000, disable SSL for reverse proxy, and add build script
  - (page) support SpecificationInterface querying in Pages collection
  - (interactions) introduce InteractionDto and IngestInteractionService (Phase 15)
  - (specifications) implement Specification Pattern for content querying (Phase 14)
  - (commands) introduce CommandBus and Micropub CQRS handlers (Phase 13)
  - (repositories) introduce ContentRepositoryInterface and FileSystemContentRepository (Phase 12)
  - (events) introduce domain events and EventDispatcher (Phase 11)
  - (domain) introduce Fqdn, Slug, and AuthToken Value Objects (Phase 10)
  - (http) extract StaticFileServer and decouple WebRouter (Phase D)
  - (repositories) implement Repository Pattern for settings and interactions (Phase C)
  - (feeds) implement strategy feed parsers and microsub domain services (Phase B)
  - (config) extract ConfigurationService domain logic and update documentation
  - (http) implement HTTP Controllers and domain Services with 3-tier tests and docs
  - (federation) implement FederationAdapter and domain Services with 3-tier tests and docs
  - (core) implement PSR-11 DI Container and eliminate global site reliance
  - (worker) decouple BackgroundWorker into modular processors (Inbox, Outbox, Archive, Webmentions)
  - (version) integrate cccp.sh predictive versioning support and hardcoded compiled build
  - (updater) implement unified Updater with 2-backup rotation, auto-versioning, CLI, and CRON integration
  - (webmention) integrate mf2 parsing, W3C discovery, CLI testing tool and IndieWeb compliance

### Bug Fixes
  - (updater) identify and compare nightly version relative to current build fixes #1
  - (psalm) resolve 847 Psalm 6 errors and suppress mf2/mf2 deprecation warning
  - (compiler) harmonize mf2 parser parameter nullability for PHP 8.4
  - (ci) prevent premature headers sent in test suite and improve socket readiness check
  - (core) declare Metadata::$title and remove obsolete global in build.php

## Previous Releases

### [nightly-20260905-151719] - update workflow configuration to optimize build steps

- Routine maintenance, documentation updates, and operational improvements.

### [nightly-20260905-102502] - remove binary artifact and update CI workflow configuration

- Routine maintenance, documentation updates, and operational improvements.

### [nightly-20260905-024545] - fix lychee binary path in CI and update site build methods

### Features
  - (reader) add search and read status filter to timeline

### Bug Fixes
  - (markdown) preserve line breaks in text nodes by applying nl2br

### [nightly-20260901-002554] - update workflow configuration to align with latest runner requirements

- Routine maintenance, documentation updates, and operational improvements.

### [nightly-20260901-000802] - update workflow configuration and runner settings

- Routine maintenance, documentation updates, and operational improvements.

### [nightly-20260831-233156] - update workflow configuration and runner settings

- Routine maintenance, documentation updates, and operational improvements.

### [nightly-20260831-231541] - update workflow configuration and runner environment settings

### Features
  - add UI and API endpoint to manage and unfollow Microsub channel feeds

### [nightly-20260830-150759] - update Microsub API endpoint from subscribe to follow

### Bug Fixes
  - update Microsub API endpoint from subscribe to follow

### [nightly-20260830-022842] - localize default flowerbed name based on page language

### Bug Fixes
  - localize default flowerbed name based on page language

### [nightly-20260830-011639] - support array-based content directories for content kinds and improve translation database initialization logic

### Features
  - support array-based content directories for content kinds and improve translation database initialization logic
  - install unzip dependency in Dockerfile for Bun support
  - (docker) migrate development environment to FrankenPHP
  - add Dockerfile and docker-compose configuration for development environment
  - add support for metapage wikilinks prefixed with % to link to site kinds or home
  - implement wiki link resolution and dynamic styling for existing and missing pages
  - transition content directory settings to language-specific arrays in ConfigHandler and Helper

### [nightly-20260720-182437] - remove support for dev mode and live-reload functionality

- Routine maintenance, documentation updates, and operational improvements.

### [nightly-20260720-173148] - replace symlink with file creation in BuildPipelineTest and update menu link assertions to target header instead of footer

### Features
  - implement automated taxonomy index page generation for tags and flowerbeds
  - add custom excerpt support and automatic post truncation for summary views
  - add advanced CLI build flags and action commands for granular site compilation and task management
  - add dedicated site rebuild action and corresponding UI notification to configuration panel
  - remove static live.js and implement dynamic fetching when dev mode is enabled
  - add dev mode toggle to configuration handler

### Bug Fixes
  - change default menu location to header and exclude intro page from navigation index
  - filter flowerbed taxonomy by kind and update layout for generated term pages

### [nightly-20260719-221945] - add new UI labels for IndieWeb interactions and AI translation notices to ConfigHandler

### Features
  - add new UI labels for IndieWeb interactions and AI translation notices to ConfigHandler
  - add translatePlural helper and refactor interaction labels to support pluralization
  - add show_in_menu configuration for site kinds and update auto-rebuild watch script

### Bug Fixes
  - correct theme path resolution logic and update url_translations table reference

### [nightly-20260717-234101] - Delete existing nightly release to prevent Gitea 404 on asset update

- Routine maintenance, documentation updates, and operational improvements.

### [nightly-20260717-232103] - Update nightly workflow to keep last 5 dated releases

### Features
  - update MicropubClientHandler and CI workflow configuration for dependency management

### Bug Fixes
  - suppress CLI warnings during tests and improve path normalization logic in SiteBuilder
  - ensure proper process resource cleanup and force termination in integration tests

### [nightly-5624092] - remove progress output flag from phpcs scripts in composer and CI workflow

- Routine maintenance, documentation updates, and operational improvements.

### [nightly-5623639] - mark Phase 21 tasks as completed in roadmap

- Routine maintenance, documentation updates, and operational improvements.

### [nightly]

### Features
  - (builder) extract PagePublisher from SiteBuilder and clean legacy delegators
  - (feeds) implement pluggable FeedGenerators and FeedPublisher consuming Entry
  - (entry) introduce universal Entry domain entity with federation and poll support
  - (database) add support for listen, watch, and read content kinds
  - (ci) add reports dashboard workflow and summary generation script
  - (ci) introduce custom CI Docker image and optimize CI workflows
  - (microsub) add webmention discovery and hashtag extraction support
  - (microsub) add ExtendedEntry and NormalizationAdapter classes
  - (federation) add yarnd, twtd, and webmention services to test environment
  - add pixelfed-worker to docker-compose and update federation test scripts for compatibility with updated API calls
  - implement setup and config CLI commands for instance initialization and variable management
  - add Caddy configuration for federation testing and automate database initialization in setup script
  - (cli) add CLI handler for profile management and post creation
  - automate local SSL certificate injection and stabilize cross-platform federation initialization scripts
  - update federation setup to create admin user and authenticate notes with API token
  - add feed URL tracking to items and implement ActivityPub follow/undo functionality during subscription changes
  - implement ActivityPub remote interaction handler and support user authentication redirects
  - add Fediverse interaction button and route to facilitate remote post engagement
  - configure Misskey template with dynamic env vars and update federation test suite account and seeding logic
  - add port configuration to Misskey federation test environment
  - add Misskey configuration file and set restart policy for mastodon-sidekiq container
  - replace mock stubs with full Mastodon, Misskey, and Pixelfed service configurations and automation scripts
  - add federation test environment with Caddy proxy, Docker Compose stubs, and setup script
  - (feat: add ActivityPub custom emoji caching and image attachment support (for Pixelfed), and update Fetcher user-agent.) feat: add ActivityPub custom emoji caching and image attachment support (for Pixelfed), and update Fetcher user-agent.
  - include public_media directory during site media copy process
  - improve user feedback during feed subscription and redirect to new channel upon creation
  - prevent duplicate feed items by checking existence in the local store before processing
  - implement remote media caching for feeds with configurable local download settings and size limits
  - add UI sync feedback, improve Atom feed parsing reliability
  - add Atom feed fallback for Pixelfed outbox and include integration test script
  - add robust fallbacks for resolving parent author names when fetching actor data fails
  - add thread link to nested quote blocks in FeedFetcher
  - add quote-post rendering and attachments support, include "Next" navigation button, and fix PDO class references
  - add ActivityPub support for fetching feeds and interacting with posts via WebFinger lookup.
  - add functionality to delete custom Microsub channels with confirmation UI
  - implement automated daily backup system with configurable rotation and directory settings
  - implement containerized multi-service architecture with automated Docker publishing and entrypoint volume initialization
  - implement local shortlink redirect file generation in SiteBuilder
  - implement link checker and add CLI command to validate internal and external links
  - (reader) add search and read status filter to timeline
  - add UI and API endpoint to manage and unfollow Microsub channel feeds
  - support array-based content directories for content kinds and improve translation database initialization logic
  - install unzip dependency in Dockerfile for Bun support
  - (docker) migrate development environment to FrankenPHP
  - add Dockerfile and docker-compose configuration for development environment
  - add support for metapage wikilinks prefixed with % to link to site kinds or home
  - implement wiki link resolution and dynamic styling for existing and missing pages
  - transition content directory settings to language-specific arrays in ConfigHandler and Helper
  - implement automated taxonomy index page generation for tags and flowerbeds
  - add custom excerpt support and automatic post truncation for summary views
  - add advanced CLI build flags and action commands for granular site compilation and task management
  - add dedicated site rebuild action and corresponding UI notification to configuration panel
  - remove static live.js and implement dynamic fetching when dev mode is enabled
  - add dev mode toggle to configuration handler
  - add new UI labels for IndieWeb interactions and AI translation notices to ConfigHandler
  - add translatePlural helper and refactor interaction labels to support pluralization
  - add show_in_menu configuration for site kinds and update auto-rebuild watch script
  - update MicropubClientHandler and CI workflow configuration for dependency management
  - add new content kinds and update theme variable scoping to prevent conflicts
  - enhance installer with configurable data directory, site settings, and initial content scaffolding
  - add original post context to reply functionality using cached timeline items
  - implement outgoing webmention sender with background processing and update UI terminology
  - implement tabbed configuration UI, add URL translation support, and automate language parity for content translations.
  - enable session-based authentication for Micropub and Microsub endpoints and remove legacy token-based UI
  - implement ActivityPub federation settings and add .env support for FQDN configuration
  - update content structure, add language support to build configuration, and reorganize localization files.
  - implement manifest-based garbage collection and build duration profiling in SiteBuilder
  - integrate local PHP development server into the file watcher process
  - add new site content, replace PHP articles with CSS versions, and rename note file
  - add theme management system to support installing, uploading, and switching between active themes
  - add Portuguese localization support and update article structures
  - support raw HTML in Markdown, restrict Micropub file uploads to allowed types, and optimize image processing with modification time checks.
  - add support for page syndication links and ActivityPub JSON-LD representation for posts
  - add garden metadata support with default tags and update site paths resolution
  - add support for IndieWeb book metadata, ActivityPub syndication, and Announce activity processing
  - add spam folder support to moderation UI and logic for Akismet filtering
  - add administrative layout wrapper, moderation status workflow, and centralized authentication check for admin handlers.
  - implement ShortlinkManager, add interaction UI tests, and update page metadata fields and API documentation
  - add Gemini and Gopher output support, improve image processing, and update site interaction displays.
  - add multi-protocol build support, update debug helpers, and populate content collection with internationalized entries
  - implement ShortlinkManager for automatic link shortening and add configuration UI
  - add timeline and mentions rendering to UI with fallback to cache and hub integration testing
  - implement Indieweb interaction types and renderers with associated Micropub handling tests
  - add Microformats2 support to views and introduce template compliance functional tests
  - add automated social image generation with dithering and SEO schema metadata injection
  - add automated API documentation generation script and documentation files for core classes
  - implement RSS and Atom feed generation with configurable post limits and per-page exclusions
  - add AI translation flagging and configureable cross-language translation parity settings
  - implement cross-language page virtualization and pseudo-translation logic in SiteBuilder
  - add p-name class to h1 elements and inject page titles in summary when missing from content
  - add configurable menu visibility and ordering for pages with functional tests
  - offload site rebuilding to background worker and update response to 202 accepted
  - add file-based locking to BackgroundWorker to prevent concurrent execution
  - implement external link archival with automated routing and force-update capability
  - implement async background queue for Webmention and ActivityPub processing via cron job
  - implement Whostyles parsing support for webmentions with validation and fallback logic
  - implement ActivityPub support for actor discovery, webfinger, and content syndication
  - add functional testing for Microsub and implement timeline pagination and feed discovery in MicrosubHandler
  - add Whostyle support to Webmention processing and display with SQLite storage migration
  - implement Microsub server support with feed fetching, channel management, and timeline endpoints
  - strip language prefixes from Twtxt content and generate separate feeds per language
  - implement clickable post kind labels with automated language-aware routing and site path resolution
  - upgrade IndieWeb support, add Whostyle integration, improve media handling, and update logo styling
  - implement Micropub and media endpoint handlers for content creation and file uploads
  - migrate from flat-file data to SQLite database with automated installation workflow
  - implement dynamic multilingual page localization, menu virtualization, and modular footer link generation
  - implement automatic virtualization of missing translations with clone support for Pages
  - implement dynamic folder localization for page slugs and categories via centralized configuration
  - add figlet logo to header and update site navigation layout to center-aligned
  - add automatic section index generation and improve URL link handling for localized site navigation
  - implement image parsing, add themedir configuration, and reorganize theme views and content structure
  - add Portuguese translation key array to the translations data file
  - add support for non-pretty URL links and implement an initial setup configuration wizard
  - implement Twtxt feed generation, parsing, and timeline synchronization features
  - implement AST-based Markdown parser and validator with PHP 8.2 upgrade
  - implement IndieAuth identity provider with OAuth 2.0 metadata, PKCE support, and secure configuration management
  - implement Webmention endpoint handler with full test coverage and routing support
  - enforce minimum PHP version 8.2.0 with runtime compatibility checks and update composer configuration
  - initialize site content with new posts, notes, photos, and update now page
  - implement single-file build system with compile.php and add comprehensive testing suites

### Bug Fixes
  - adjust directory permissions for Mastodon and Misskey containers during federation setup
  - add db:prepare step to mastodon container startup in federation tests
  - (markdown) preserve line breaks in text nodes by applying nl2br
  - update Microsub API endpoint from subscribe to follow
  - localize default flowerbed name based on page language
  - change default menu location to header and exclude intro page from navigation index
  - filter flowerbed taxonomy by kind and update layout for generated term pages
  - correct theme path resolution logic and update url_translations table reference
  - suppress CLI warnings during tests and improve path normalization logic in SiteBuilder
  - ensure proper process resource cleanup and force termination in integration tests
  - ensure session is started before authentication check in request handlers
  - enforce strict route matching by anchoring regex patterns to start of URI
  - correct language prefix stripping logic in Markdown processor and add theme data abstraction to roadmap
  - resolve translation parity mismatches and language slug bugs
  - update ReflectionProperty::setValue calls and ignore sqlite journal files
  - correct CSS property, remove escaped backslashes, and standardize PDO fetch and bind methods in IndieAuthHandler
  - strip .html extension from nick when prettylinks is disabled to prevent duplicate extensions
  - resolve broken image paths in markdown by prepending root-relative directory to gif src
  - improve type safety, static analysis, and site configuration handling across application components.
  - update YAML regex to support multi-line front matter and add functional test suite with vfsStream dependency
  - repair build pipeline with autoloader fixes, config case mapping, and Page class ArrayAccess bridge
fix head bug
fix scan and add str_start_with and recursive_rmdir

