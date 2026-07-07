# Paths
**Namespace:** `Indieinabox\Site`

Class Paths

Holds directory paths related to the site.

## Properties

### `public string $baseDir`

@var string

### `public string $outputDirHtml`

@var string

### `public string $outputDirGemini`

@var string

### `public string $outputDirGopher`

@var string

### `public string $outputDirMedia`

@var string

### `public string $contentDir`

@var string

### `public string $themeDir`

@var string

## Methods

### __construct()
`public function __construct(string $baseDir = '/', string $outputDirHtml = 'public_html', string $outputDirGemini = 'public_gemini', string $outputDirGopher = 'public_gopher', string $outputDirMedia = 'public_html/media', string $contentDir = 'content', string $themeDir = 'resources')`

SitePaths constructor.

@param string $baseDir
@param string $outputDirHtml
@param string $outputDirGemini
@param string $outputDirGopher
@param string $outputDirMedia
@param string $contentDir
@param string $themeDir

### getContentPath()
`public function getContentPath(): string`

Retrieves the absolute path to the content directory.

@return string
