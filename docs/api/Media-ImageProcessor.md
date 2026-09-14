# ImageProcessor
**Namespace:** `Indieinabox\Media`

Class ImageProcessor

Provides GD-based image operations, Atkinson dithering algorithms,
thumbnail generation, and social media card rendering.

## Methods

### createThumbnail()
`public static function createThumbnail(string $caminhoOriginal, string $caminhoDestino, int $tamanhoFocal, array $corBG, array $corFG): bool`

Create a small thumbnail using GD and the global palette.

@param string $caminhoOriginal
@param string $caminhoDestino
@param int $tamanhoFocal
@param array<int, int> $corBG
@param array<int, int> $corFG
@return bool

### ditherImageToGif()
`public static function ditherImageToGif(string $caminhoOriginal, string $caminhoDestino, int $larguraFocal, array $corBG, array $corFG, bool $aplicarAutomacao = true): bool`

Atkinson adaptive dithering using GD to index 8-bit GIF

@param string $caminhoOriginal
@param string $caminhoDestino
@param int $larguraFocal
@param array<int, int> $corBG
@param array<int, int> $corFG
@param bool $aplicarAutomacao
@return bool

### ditherAndCropImageToPng()
`public static function ditherAndCropImageToPng(string $caminhoOriginal, string $caminhoDestino, int $targetWidth, int $targetHeight, array $corBG, array $corFG, bool $aplicarAutomacao = true): bool`

Atkinson adaptive dithering with cropping to exact dimensions, saved as PNG

@codeCoverageIgnore
@param string $caminhoOriginal
@param string $caminhoDestino
@param int $targetWidth
@param int $targetHeight
@param array<int, int> $corBG
@param array<int, int> $corFG
@param bool $aplicarAutomacao
@return bool

### generateSocialImages()
`public static function generateSocialImages(string $caminhoOriginal, string $caminhoDestinoBase, array $corBG, array $corFG): array`

@codeCoverageIgnore

Generate social media images (OG, JSON-LD sizes)

@param string $caminhoOriginal
@param string $caminhoDestinoBase
@param array<int, int> $corBG
@param array<int, int> $corFG
@return array<string, string>
