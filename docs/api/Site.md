# Site
**Namespace:** `Indieinabox`

Class Site

Represents the global site configuration and state for the Indieinabox application.
It acts as a central registry for all configuration components such as metadata,
paths, localization, and feature flags, providing a unified interface to access them.

## Properties

### `public array $config`

@var array<string, mixed> The raw configuration array loaded from the database or config file.

### `public Indieinabox\Site\Metadata $metadata`

@var Metadata Contains metadata about the site (e.g., title, author, description).

### `public Indieinabox\Site\Paths $paths`

@var Paths Stores all relevant directory and file paths used during site generation.

### `public Indieinabox\Site\Options $options`

@var Options Contains boolean flags and global options (e.g., dev mode, pretty links).

### `public Indieinabox\Site\Localization $localization`

@var Localization Handles language settings and translations for the site.

### `public Indieinabox\Site\Support $support`

@var Support Contains feature support settings (e.g., specific protocol or format toggles).

### `public Indieinabox\Site\Twtxt $twtxt`

@var Twtxt Contains Twtxt specific configurations, such as nickname and following list.

## Methods

### __construct()
`public function __construct(?Indieinabox\Site\Metadata $metadata = null, ?Indieinabox\Site\Paths $paths = null, ?Indieinabox\Site\Options $options = null, ?Indieinabox\Site\Localization $localization = null, ?Indieinabox\Site\Support $support = null, ?Indieinabox\Site\Twtxt $twtxt = null)`

Site constructor.

Initializes a new Site configuration object. If any component is not provided,
it instantiates a default version of that component.

@param Metadata|null $metadata Optional metadata configuration.
@param Paths|null $paths Optional paths configuration.
@param Options|null $options Optional global options configuration.
@param Localization|null $localization Optional localization settings.
@param Support|null $support Optional support features configuration.
@param Twtxt|null $twtxt Optional Twtxt configuration.

### __get()
`public function __get(string $name)`

Magic getter for backward compatibility and quick access to deeply nested properties.

@param string $name The name of the property to retrieve.
@return mixed The corresponding configuration value or null if not found.
