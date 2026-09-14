<?php

declare(strict_types=1);

namespace Indieinabox\Media;

/**
 * Class ImageProcessor
 *
 * Provides GD-based image operations, Atkinson dithering algorithms,
 * thumbnail generation, and social media card rendering.
 */
class ImageProcessor
{
    /**
     * Create a small thumbnail using GD and the global palette.
     *
     * @param string $caminhoOriginal
     * @param string $caminhoDestino
     * @param int $tamanhoFocal
     * @param array<int, int> $corBG
     * @param array<int, int> $corFG
     * @return bool
     */
    public static function createThumbnail(
        string $caminhoOriginal,
        string $caminhoDestino,
        int $tamanhoFocal,
        array $corBG,
        array $corFG
    ): bool {
        if (!is_dir(dirname($caminhoDestino))) {
            mkdir(dirname($caminhoDestino), 0777, true);
        }

        if (file_exists($caminhoDestino) && file_exists($caminhoOriginal)) {
            if (filemtime($caminhoDestino) >= filemtime($caminhoOriginal)) {
                return true;
            }
        }

        $imageInfo = @getimagesize($caminhoOriginal);
        if (!$imageInfo) {
            return false;
        }
        $mimeType = $imageInfo['mime'];

        if ($mimeType === 'image/png') {
            $imgOriginal = @imagecreatefrompng($caminhoOriginal);
        } elseif ($mimeType === 'image/gif') {
            $imgOriginal = @imagecreatefromgif($caminhoOriginal);
        } elseif ($mimeType === 'image/webp') {
            $imgOriginal = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($caminhoOriginal) : false;
        } else {
            $imgOriginal = @imagecreatefromjpeg($caminhoOriginal);
            if ($imgOriginal && function_exists('exif_read_data')) {
                $exif = @exif_read_data($caminhoOriginal);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $imgOriginal = imagerotate($imgOriginal, 180, 0);
                            break;
                        case 6:
                            $imgOriginal = imagerotate($imgOriginal, -90, 0);
                            break;
                        case 8:
                            $imgOriginal = imagerotate($imgOriginal, 90, 0);
                            break;
                    }
                }
            }
        }

        if (!$imgOriginal) {
            return false;
        }

        $larguraOrig = imagesx($imgOriginal);
        $alturaOrig = imagesy($imgOriginal);

        $srcX = 0;
        $srcY = 0;

        if ($larguraOrig > $alturaOrig) {
            $srcX = (int)(($larguraOrig - $alturaOrig) / 2);
            $larguraOrig = $alturaOrig;
        } else {
            $srcY = (int)(($alturaOrig - $larguraOrig) / 2);
            $alturaOrig = $larguraOrig;
        }

        $imgRedimensionada = imagecreatetruecolor($tamanhoFocal, $tamanhoFocal);
        imagecopyresampled(
            $imgRedimensionada,
            $imgOriginal,
            0,
            0,
            $srcX,
            $srcY,
            $tamanhoFocal,
            $tamanhoFocal,
            $larguraOrig,
            $alturaOrig
        );

        $imgFinal = imagecreate($tamanhoFocal, $tamanhoFocal);
        $allocatedBG = imagecolorallocate($imgFinal, $corBG[0], $corBG[1], $corBG[2]);
        $allocatedFG = imagecolorallocate($imgFinal, $corFG[0], $corFG[1], $corFG[2]);

        for ($y = 0; $y < $tamanhoFocal; $y++) {
            for ($x = 0; $x < $tamanhoFocal; $x++) {
                $rgb = imagecolorat($imgRedimensionada, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $luminosidade = ($r * 0.299 + $g * 0.587 + $b * 0.114);

                $cor = ($luminosidade > 128) ? $allocatedBG : $allocatedFG;
                imagesetpixel($imgFinal, $x, $y, $cor);
            }
        }

        $result = imagegif($imgFinal, $caminhoDestino);

        return $result;
    }

    /**
     * Atkinson adaptive dithering using GD to index 8-bit GIF
     *
     * @param string $caminhoOriginal
     * @param string $caminhoDestino
     * @param int $larguraFocal
     * @param array<int, int> $corBG
     * @param array<int, int> $corFG
     * @param bool $aplicarAutomacao
     * @return bool
     */
    public static function ditherImageToGif(
        string $caminhoOriginal,
        string $caminhoDestino,
        int $larguraFocal,
        array $corBG,
        array $corFG,
        bool $aplicarAutomacao = true
    ): bool {
        if (!is_dir(dirname($caminhoDestino))) {
            mkdir(dirname($caminhoDestino), 0777, true);
        }

        if (file_exists($caminhoDestino) && file_exists($caminhoOriginal)) {
            if (filemtime($caminhoDestino) >= filemtime($caminhoOriginal)) {
                return true;
            }
        }

        $imageInfo = @getimagesize($caminhoOriginal);
        if (!$imageInfo) {
            return false;
        }
        $mimeType = $imageInfo['mime'];

        if ($mimeType === 'image/png') {
            $imgOriginal = @imagecreatefrompng($caminhoOriginal);
        } elseif ($mimeType === 'image/gif') {
            $imgOriginal = @imagecreatefromgif($caminhoOriginal);
        } elseif ($mimeType === 'image/webp') {
            $imgOriginal = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($caminhoOriginal) : false;
        } else {
            $imgOriginal = @imagecreatefromjpeg($caminhoOriginal);
            if ($imgOriginal && function_exists('exif_read_data')) {
                $exif = @exif_read_data($caminhoOriginal);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $imgOriginal = imagerotate($imgOriginal, 180, 0);
                            break;
                        case 6:
                            $imgOriginal = imagerotate($imgOriginal, -90, 0);
                            break;
                        case 8:
                            $imgOriginal = imagerotate($imgOriginal, 90, 0);
                            break;
                    }
                }
            }
        }

        if (!$imgOriginal) {
            return false;
        }

        $larguraOrig = imagesx($imgOriginal);
        $alturaOrig = imagesy($imgOriginal);
        $alturaFocal = (int)(($alturaOrig / $larguraOrig) * $larguraFocal);

        $imgRedimensionada = imagecreatetruecolor($larguraFocal, $alturaFocal);
        imagecopyresampled(
            $imgRedimensionada,
            $imgOriginal,
            0,
            0,
            0,
            0,
            $larguraFocal,
            $alturaFocal,
            $larguraOrig,
            $alturaOrig
        );

        $brilhoTotal = 0;
        $amostras = 0;
        for ($y = 0; $y < $alturaFocal; $y += 10) {
            for ($x = 0; $x < $larguraFocal; $x += 10) {
                $rgb = imagecolorat($imgRedimensionada, $x, $y);
                $brilhoTotal += ((($rgb >> 16) & 0xFF) * 0.299
                    + (($rgb >> 8) & 0xFF) * 0.587
                    + ($rgb & 0xFF) * 0.114);
                $amostras++;
            }
        }
        $luminanciaMedia = ($brilhoTotal / $amostras) / 255;

        $fatorGamma = 1.0;
        $fatorContraste = 1.0;

        if ($aplicarAutomacao) {
            $alvoLuminancia = 0.40;
            $desvio = $luminanciaMedia - $alvoLuminancia;
            $fatorGamma = 1.0 + ($desvio * 0.65);
            $fatorContraste = 1.0 + (abs($desvio) * 0.20);
        }

        $matrix = [];
        for ($y = 0; $y < $alturaFocal; $y++) {
            for ($x = 0; $x < $larguraFocal; $x++) {
                $rgb = imagecolorat($imgRedimensionada, $x, $y);
                $v = ((($rgb >> 16) & 0xFF) * 0.299 + (($rgb >> 8) & 0xFF) * 0.587 + ($rgb & 0xFF) * 0.114) / 255;

                if ($aplicarAutomacao) {
                    $v = pow($v, $fatorGamma);
                    $v = (($v - 0.5) * $fatorContraste) + 0.5;
                }

                $matrix[$y][$x] = max(0, min(1, $v)) * 255;
            }
        }

        for ($y = 0; $y < $alturaFocal; $y++) {
            for ($x = 0; $x < $larguraFocal; $x++) {
                $oldPixel = $matrix[$y][$x];
                $newPixel = ($oldPixel > 128) ? 255 : 0;
                $matrix[$y][$x] = $newPixel;

                $errorVal = ($oldPixel - $newPixel) / 8;

                if ($x + 1 < $larguraFocal) {
                    $matrix[$y][$x + 1] += $errorVal;
                }
                if ($x + 2 < $larguraFocal) {
                    $matrix[$y][$x + 2] += $errorVal;
                }
                if ($y + 1 < $alturaFocal) {
                    if ($x - 1 >= 0) {
                        $matrix[$y + 1][$x - 1] += $errorVal;
                    }
                    $matrix[$y + 1][$x] += $errorVal;
                    if ($x + 1 < $larguraFocal) {
                        $matrix[$y + 1][$x + 1] += $errorVal;
                    }
                }
                if ($y + 2 < $alturaFocal) {
                    $matrix[$y + 2][$x] += $errorVal;
                }
            }
        }

        $imgFinal = imagecreate($larguraFocal, $alturaFocal);
        $allocatedBG = imagecolorallocate($imgFinal, $corBG[0], $corBG[1], $corBG[2]);
        $allocatedFG = imagecolorallocate($imgFinal, $corFG[0], $corFG[1], $corFG[2]);

        for ($y = 0; $y < $alturaFocal; $y++) {
            for ($x = 0; $x < $larguraFocal; $x++) {
                $color = ($matrix[$y][$x] > 128) ? $allocatedBG : $allocatedFG;
                imagesetpixel($imgFinal, $x, $y, $color);
            }
        }

        $result = imagegif($imgFinal, $caminhoDestino);

        return $result;
    }

    /**
     * Atkinson adaptive dithering with cropping to exact dimensions, saved as PNG
     *
     * @codeCoverageIgnore
     * @param string $caminhoOriginal
     * @param string $caminhoDestino
     * @param int $targetWidth
     * @param int $targetHeight
     * @param array<int, int> $corBG
     * @param array<int, int> $corFG
     * @param bool $aplicarAutomacao
     * @return bool
     */
    public static function ditherAndCropImageToPng(
        string $caminhoOriginal,
        string $caminhoDestino,
        int $targetWidth,
        int $targetHeight,
        array $corBG,
        array $corFG,
        bool $aplicarAutomacao = true
    ): bool {
        if (!is_dir(dirname($caminhoDestino))) {
            mkdir(dirname($caminhoDestino), 0777, true);
        }

        $ext = strtolower(pathinfo($caminhoOriginal, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $imgOriginal = @imagecreatefrompng($caminhoOriginal);
        } elseif ($ext === 'gif') {
            $imgOriginal = @imagecreatefromgif($caminhoOriginal);
        } elseif ($ext === 'webp') {
            $imgOriginal = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($caminhoOriginal) : false;
        } else {
            $imgOriginal = @imagecreatefromjpeg($caminhoOriginal);
            if ($imgOriginal && function_exists('exif_read_data')) {
                $exif = @exif_read_data($caminhoOriginal);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $imgOriginal = imagerotate($imgOriginal, 180, 0);
                            break;
                        case 6:
                            $imgOriginal = imagerotate($imgOriginal, -90, 0);
                            break;
                        case 8:
                            $imgOriginal = imagerotate($imgOriginal, 90, 0);
                            break;
                    }
                }
            }
        }

        if (!$imgOriginal) {
            return false;
        }

        $origWidth = imagesx($imgOriginal);
        $origHeight = imagesy($imgOriginal);

        $targetRatio = $targetWidth / $targetHeight;
        $origRatio = $origWidth / $origHeight;

        if ($origRatio > $targetRatio) {
            $cropHeight = $origHeight;
            $cropWidth = (int)($origHeight * $targetRatio);
            $cropX = (int)(($origWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            $cropWidth = $origWidth;
            $cropHeight = (int)($origWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int)(($origHeight - $cropHeight) / 2);
        }

        $imgRedimensionada = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled(
            $imgRedimensionada,
            $imgOriginal,
            0,
            0,
            $cropX,
            $cropY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );

        $brilhoTotal = 0;
        $amostras = 0;
        for ($y = 0; $y < $targetHeight; $y += 10) {
            for ($x = 0; $x < $targetWidth; $x += 10) {
                $rgb = imagecolorat($imgRedimensionada, $x, $y);
                $brilhoTotal += ((($rgb >> 16) & 0xFF) * 0.299
                    + (($rgb >> 8) & 0xFF) * 0.587
                    + ($rgb & 0xFF) * 0.114);
                $amostras++;
            }
        }
        $luminanciaMedia = ($brilhoTotal / $amostras) / 255;

        $fatorGamma = 1.0;
        $fatorContraste = 1.0;

        if ($aplicarAutomacao) {
            $alvoLuminancia = 0.40;
            $desvio = $luminanciaMedia - $alvoLuminancia;
            $fatorGamma = 1.0 + ($desvio * 0.65);
            $fatorContraste = 1.0 + (abs($desvio) * 0.20);
        }

        $matrix = [];
        for ($y = 0; $y < $targetHeight; $y++) {
            for ($x = 0; $x < $targetWidth; $x++) {
                $rgb = imagecolorat($imgRedimensionada, $x, $y);
                $v = ((($rgb >> 16) & 0xFF) * 0.299 + (($rgb >> 8) & 0xFF) * 0.587 + ($rgb & 0xFF) * 0.114) / 255;

                if ($aplicarAutomacao) {
                    $v = pow($v, $fatorGamma);
                    $v = (($v - 0.5) * $fatorContraste) + 0.5;
                }

                $matrix[$y][$x] = max(0, min(1, $v)) * 255;
            }
        }

        for ($y = 0; $y < $targetHeight; $y++) {
            for ($x = 0; $x < $targetWidth; $x++) {
                $oldPixel = $matrix[$y][$x];
                $newPixel = ($oldPixel > 128) ? 255 : 0;
                $matrix[$y][$x] = $newPixel;

                $errorVal = ($oldPixel - $newPixel) / 8;

                if ($x + 1 < $targetWidth) {
                    $matrix[$y][$x + 1] += $errorVal;
                }
                if ($x + 2 < $targetWidth) {
                    $matrix[$y][$x + 2] += $errorVal;
                }
                if ($y + 1 < $targetHeight) {
                    if ($x - 1 >= 0) {
                        $matrix[$y + 1][$x - 1] += $errorVal;
                    }
                    $matrix[$y + 1][$x] += $errorVal;
                    if ($x + 1 < $targetWidth) {
                        $matrix[$y + 1][$x + 1] += $errorVal;
                    }
                }
                if ($y + 2 < $targetHeight) {
                    $matrix[$y + 2][$x] += $errorVal;
                }
            }
        }

        $imgFinal = imagecreate($targetWidth, $targetHeight);
        $allocatedBG = imagecolorallocate($imgFinal, $corBG[0], $corBG[1], $corBG[2]);
        $allocatedFG = imagecolorallocate($imgFinal, $corFG[0], $corFG[1], $corFG[2]);

        for ($y = 0; $y < $targetHeight; $y++) {
            for ($x = 0; $x < $targetWidth; $x++) {
                $color = ($matrix[$y][$x] > 128) ? $allocatedBG : $allocatedFG;
                imagesetpixel($imgFinal, $x, $y, $color);
            }
        }

        $result = imagepng($imgFinal, $caminhoDestino, 8);

        return $result;
    }

    /**
     * @codeCoverageIgnore
     *
     * Generate social media images (OG, JSON-LD sizes)
     *
     * @param string $caminhoOriginal
     * @param string $caminhoDestinoBase
     * @param array<int, int> $corBG
     * @param array<int, int> $corFG
     * @return array<string, string>
     */
    public static function generateSocialImages(
        string $caminhoOriginal,
        string $caminhoDestinoBase,
        array $corBG,
        array $corFG
    ): array {
        $sizes = [
            '1200x630' => [1200, 630],
            '1920x1080' => [1920, 1080],
            '1440x1080' => [1440, 1080],
            '1080x1080' => [1080, 1080],
        ];

        $results = [];
        $dir = dirname($caminhoDestinoBase);
        $filename = pathinfo($caminhoDestinoBase, PATHINFO_FILENAME);

        foreach ($sizes as $suffix => $dims) {
            $dest = $dir . DIRECTORY_SEPARATOR . $filename . '_' . $suffix . '.png';
            if (self::ditherAndCropImageToPng($caminhoOriginal, $dest, $dims[0], $dims[1], $corBG, $corFG)) {
                $results[$suffix] = $dest;
            }
        }

        return $results;
    }
}
