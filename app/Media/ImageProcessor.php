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

        $larguraOrig = (int) imagesx($imgOriginal);
        $alturaOrig = (int) imagesy($imgOriginal);

        $srcX = 0;
        $srcY = 0;

        if ($larguraOrig > $alturaOrig) {
            $srcX = (int)((float)($larguraOrig - $alturaOrig) / 2.0);
            $larguraOrig = $alturaOrig;
        } else {
            $srcY = (int)((float)($alturaOrig - $larguraOrig) / 2.0);
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
                $luminosidade = ((float)$r * 0.299 + (float)$g * 0.587 + (float)$b * 0.114);

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
        $alturaFocal = (int)(((float)$alturaOrig / (float)$larguraOrig) * (float)$larguraFocal);

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

        $brilhoTotal = 0.0;
        $amostras = 0;
        for ($y = 0; $y < $alturaFocal; $y += 10) {
            for ($x = 0; $x < $larguraFocal; $x += 10) {
                $rgb = imagecolorat($imgRedimensionada, $x, $y);
                $brilhoTotal += ((float)(($rgb >> 16) & 0xFF) * 0.299
                    + (float)(($rgb >> 8) & 0xFF) * 0.587
                    + (float)($rgb & 0xFF) * 0.114);
                $amostras++;
            }
        }
        $luminanciaMedia = ($brilhoTotal / (float)$amostras) / 255.0;

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
                $v = ((float)(($rgb >> 16) & 0xFF) * 0.299 + (float)(($rgb >> 8) & 0xFF) * 0.587 + (float)($rgb & 0xFF) * 0.114) / 255.0;

                if ($aplicarAutomacao) {
                    $v = pow($v, $fatorGamma);
                    $v = (($v - 0.5) * $fatorContraste) + 0.5;
                }

                $matrix[$y][$x] = max(0.0, min(1.0, $v)) * 255.0;
            }
        }

        for ($y = 0; $y < $alturaFocal; $y++) {
            for ($x = 0; $x < $larguraFocal; $x++) {
                $oldPixel = $matrix[$y][$x];
                $newPixel = ($oldPixel > 128) ? 255 : 0;
                $matrix[$y][$x] = (float)$newPixel;

                $errorVal = ($oldPixel - (float)$newPixel) / 8.0;

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

        $targetRatio = (float)$targetWidth / (float)$targetHeight;
        $origRatio = (float)$origWidth / (float)$origHeight;

        if ($origRatio > $targetRatio) {
            $cropHeight = $origHeight;
            $cropWidth = (int)((float)$origHeight * $targetRatio);
            $cropX = (int)(((float)$origWidth - (float)$cropWidth) / 2.0);
            $cropY = 0;
        } else {
            $cropWidth = $origWidth;
            $cropHeight = (int)((float)$origWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int)(((float)$origHeight - (float)$cropHeight) / 2.0);
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

        $brilhoTotal = 0.0;
        $amostras = 0;
        for ($y = 0; $y < $targetHeight; $y += 10) {
            for ($x = 0; $x < $targetWidth; $x += 10) {
                $rgb = imagecolorat($imgRedimensionada, $x, $y);
                $brilhoTotal += ((float)(($rgb >> 16) & 0xFF) * 0.299
                    + (float)(($rgb >> 8) & 0xFF) * 0.587
                    + (float)($rgb & 0xFF) * 0.114);
                $amostras++;
            }
        }
        $luminanciaMedia = ($brilhoTotal / (float)$amostras) / 255.0;

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
                $v = ((float)(($rgb >> 16) & 0xFF) * 0.299 + (float)(($rgb >> 8) & 0xFF) * 0.587 + (float)($rgb & 0xFF) * 0.114) / 255.0;

                if ($aplicarAutomacao) {
                    $v = pow($v, $fatorGamma);
                    $v = (($v - 0.5) * $fatorContraste) + 0.5;
                }

                $matrix[$y][$x] = max(0.0, min(1.0, $v)) * 255.0;
            }
        }

        for ($y = 0; $y < $targetHeight; $y++) {
            for ($x = 0; $x < $targetWidth; $x++) {
                $oldPixel = $matrix[$y][$x];
                $newPixel = ($oldPixel > 128) ? 255 : 0;
                $matrix[$y][$x] = (float)$newPixel;

                $errorVal = ($oldPixel - (float)$newPixel) / 8.0;

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

    /**
     * Converts any supported image format to a Mosaic-compatible baseline sequential JPEG.
     * Enforces imageinterlace = 0 (baseline) and sRGB 24-bit color representation.
     *
     * @param string $caminhoOriginal Source image path
     * @param string $caminhoDestino Target JPEG path
     * @param int $qualidade JPEG compression quality (default 85)
     * @return bool
     */
    public static function convertToBaselineJpeg(
        string $caminhoOriginal,
        string $caminhoDestino,
        int $qualidade = 85
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

        $width = imagesx($imgOriginal);
        $height = imagesy($imgOriginal);

        // Render onto a truecolor surface with white background for alpha channels
        $trueColor = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($trueColor, 255, 255, 255);
        imagefilledrectangle($trueColor, 0, 0, $width, $height, $white);
        imagecopy($trueColor, $imgOriginal, 0, 0, 0, 0, $width, $height);

        // Explicitly enforce Baseline (Sequential) JPEG: interlace must be false / 0
        imageinterlace($trueColor, false);

        $saved = imagejpeg($trueColor, $caminhoDestino, $qualidade);

        return $saved;
    }

    /**
     * Embeds license and copyright metadata comment into JPEG or GIF binaries.
     * For JPEG: writes a standard COM marker (0xFF 0xFE).
     * For GIF: writes a GIF89a Comment Extension block (0x21 0xFE).
     * Both techniques are 100% compatible with NCSA Mosaic and legacy viewers.
     *
     * @param string $filePath Absolute path to the image file
     * @param string $commentText Comment or license string to embed
     * @return bool True on success, false otherwise
     */
    public static function embedMetadata(string $filePath, string $commentText): bool
    {
        if (!file_exists($filePath) || empty($commentText)) {
            return false;
        }

        $data = file_get_contents($filePath);
        if ($data === false || strlen($data) < 4) {
            return false;
        }

        // 1. JPEG check (starts with 0xFF 0xD8)
        if (substr($data, 0, 2) === chr(0xFF) . chr(0xD8)) {
            // Check if comment already exists
            if (str_contains($data, $commentText)) {
                return true;
            }

            // COM marker format: 0xFF 0xFE + 2-byte length (big-endian, including length bytes) + payload
            $commentBytes = $commentText;
            $length = strlen($commentBytes) + 2;
            if ($length > 65535) {
                $commentBytes = substr($commentBytes, 0, 65533);
                $length = strlen($commentBytes) + 2;
            }
            $comBlock = chr(0xFF) . chr(0xFE) . pack('n', $length) . $commentBytes;

            // Insert COM marker immediately after SOI (0xFF 0xD8)
            $newData = substr($data, 0, 2) . $comBlock . substr($data, 2);
            return file_put_contents($filePath, $newData) !== false;
        }

        // 2. GIF check (starts with GIF87a or GIF89a)
        if (substr($data, 0, 3) === 'GIF') {
            if (str_contains($data, $commentText)) {
                return true;
            }

            // Ensure GIF89a header for comment extension support
            $header = 'GIF89a' . substr($data, 6);

            // Find trailer (0x3B = ';') from end
            $trailerPos = strrpos($header, chr(0x3B));
            if ($trailerPos === false) {
                $trailerPos = strlen($header);
            }

            // GIF Comment Extension: 0x21 0xFE + chunks (sub-block size 1-255 + data) + 0x00 terminator
            $chunks = '';
            $offset = 0;
            $commentLen = strlen($commentText);
            while ($offset < $commentLen) {
                $chunk = substr($commentText, $offset, 255);
                $chunks .= chr(strlen($chunk)) . $chunk;
                $offset += 255;
            }
            $commentExtension = chr(0x21) . chr(0xFE) . $chunks . chr(0);

            // Insert comment extension right before trailer
            $newData = substr($header, 0, $trailerPos) . $commentExtension . chr(0x3B);
            return file_put_contents($filePath, $newData) !== false;
        }

        return false;
    }
}
